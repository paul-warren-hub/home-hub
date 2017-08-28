#
# Home Automation Hub
# Helper Script to process Met Office data
#
# pylint: disable=broad-except
# pylint: disable=too-many-locals
#

"""Query the Met-Office DataPoint Service"""

import sys
import logging
import datetime
import dateutil.parser
import requests
import requests_cache
from lxml import etree
import helpers

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')
WIND_DIRECTIONS = {'N': 0, 'NNE': 22.5, 'NE': 45, 'ENE': 67.5, 'E': 90,
                   'ESE': 112.5, 'SE': 135, 'SSE': 157.5, 'S': 180,
                   'SSW': 202.5, 'SW': 225, 'WSW': 247.5, 'W': 270,
                   'WNW': 292.5, 'NW': 315, 'NNW': 337.5}

# Transparently adds caching to all http Requests
requests_cache.install_cache(
    'meteorology_cache',
    backend='sqlite',
    expire_after=3600)


def meteorology(cur_val, method_params):

    """Query the Met-Office DataPoint Service"""

    # <SiteRep>
    # <Wx>
    #   <Param name="F" units="C">Feels Like Temperature</Param>
    #   <Param name="G" units="mph">Wind Gust</Param>
    #   <Param name="H" units="%">Screen Relative Humidity</Param>
    #   <Param name="T" units="C">Temperature</Param>
    #   <Param name="V" units="">Visibility</Param>
    #   <Param name="D" units="compass">Wind Direction</Param>
    #   <Param name="S" units="mph">Wind Speed</Param>
    #   <Param name="U" units="">Max UV Index</Param>
    #   <Param name="W" units="">Weather Type</Param>
    #   <Param name="Pp" units="%">Precipitation Probability</Param>
    # </Wx>
    # <DV dataDate="2016-10-22T07:00:00Z" type="Forecast">
    #   <Location i="3853" lat="51.006" lon="-2.64" name="YEOVILTON"
    #       country="ENGLAND" continent="EUROPE" elevation="20.0">
    #       <Period type="Day" value="2016-10-22Z">
    #           <Rep D="SE" F="0" G="9" H="99"
    #               Pp="16" S="2" T="2" V="VP" W="6" U="0">180</Rep>
    #           <Rep D="N" F="-1" G="11" H="99"
    #               Pp="18" S="0" T="0" V="VP" W="6" U="0">360</Rep>
    #           <Rep D="NE" F="3" G="13" H="95"
    #               Pp="18" S="7" T="5" V="PO" W="5" U="1">540</Rep>
    # ...

    result = "Null"  # Never fail these!
    try:
        # meteorology.S.{0|180|360|540}
        HUB_LOGGER.debug("Current Value: %s", cur_val)
        if method_params:

            measurand = method_params[0]
            forecast_look_ahead_mins = 0   # default
            if len(method_params) > 1:
                forecast_look_ahead_mins = int(method_params[1])

            api_key = helpers.get_user_setting("DataPointKey")
            location_id = int(helpers.get_user_setting("DataPointLocation"))
            forecast_freq_text = "3hourly"

            # set up request query string parameters
            qs_params = {'res': forecast_freq_text, 'key': api_key}

            # find nearest forecast
            chosen_fcast_date_time = find_nearest_forecast(
                forecast_look_ahead_mins,
                qs_params)

            # then get forecast
            result = get_forecast_value(
                chosen_fcast_date_time, location_id, qs_params, measurand)

        # return result and optional timestamp
        return (result, chosen_fcast_date_time)

    except Exception:
        HUB_LOGGER.error("Unable to read datapoint")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)


def find_nearest_forecast(fcast_look_ahead_mins, payload):

    """Find nearest forecast"""

    # find nearest forecast to current time
    # - half period either side of current
    time_series_url = (
        'http://datapoint.metoffice.gov.uk/'
        'public/data/val/wxfcs/all/xml/capabilities')
    # make the request
    req = requests.get(time_series_url, params=payload, timeout=20)
    HUB_LOGGER.info("attempting to reach remote timeseries url: %s"
                    "status: %s from cache: %s",
                    req.url, req.status_code, req.from_cache)
    # find the result body - will be an xml document
    xml_time_result = req.content
    HUB_LOGGER.info("meteorology time series xml %s", xml_time_result[:48])
    cur_iso_date_time = (
        datetime.datetime.now() +
        datetime.timedelta(
            minutes=fcast_look_ahead_mins)).isoformat()
    HUB_LOGGER.info("meteorology current time iso %s", cur_iso_date_time)
    # lookup this forecast time
    recent_fcast_date_time = cur_iso_date_time
    upcoming_fcast_date_time = cur_iso_date_time
    if xml_time_result.startswith("<?xml"):
        time_tree = etree.fromstring(xml_time_result)
        fcast_times = time_tree.xpath("//text()", smart_strings=False)
        HUB_LOGGER.info("Prev fcast_times: %s", len(fcast_times))
        if fcast_times:
            # iterate through forecast times
            # finding most recent and upcoming
            # this will work over date boundaries
            matches = (fcastTime for fcastTime in fcast_times)
            while upcoming_fcast_date_time <= cur_iso_date_time:
                recent_fcast_date_time = upcoming_fcast_date_time
                upcoming_fcast_date_time = matches.next()

    HUB_LOGGER.info("Upcoming fcastTime: %s", upcoming_fcast_date_time)
    HUB_LOGGER.info("Recent fcastTime: %s", recent_fcast_date_time)
    # now determine which is nearest
    cur_date_obj = (
        dateutil.parser.parse(cur_iso_date_time).replace(tzinfo=None))
    rec_iso_date_obj = (
        dateutil.parser.parse(recent_fcast_date_time).replace(tzinfo=None))
    upcoming_iso_date_obj = (
        dateutil.parser.parse(upcoming_fcast_date_time).replace(tzinfo=None))
    expired_for = cur_date_obj - rec_iso_date_obj
    awaited_for = upcoming_iso_date_obj - cur_date_obj
    HUB_LOGGER.info("Expired For: %s Awaited For: %s",
                    expired_for,
                    awaited_for)
    look_forward = awaited_for < expired_for
    look_back = expired_for <= awaited_for
    if look_forward:
        chosen_forecast_date_time = upcoming_fcast_date_time
    else:
        chosen_forecast_date_time = recent_fcast_date_time
    HUB_LOGGER.info(
        "Look Forward: %s Look Back: %s Chosen Forecast: %s",
        look_forward,
        look_back,
        chosen_forecast_date_time)
    return chosen_forecast_date_time


def get_forecast_value(
        chosen_fcast_date_time, location_id, payload, measurand):

    """Get forecast"""

    # <Period type="Day" value="2016-10-29Z">
    #   <Rep D="SSE" F="12" G="7" H="97"
    #       Pp="10" S="2" T="12" V="MO" W="8" U="0">180</Rep>
    #   <Rep D="E" F="12" G="2" H="98"
    #       Pp="10" S="2" T="12" V="MO" W="8" U="0">360</Rep>
    #   <Rep D="N" F="12" G="2" H="95"
    #       Pp="11" S="2" T="12" V="MO" W="8" U="1">540</Rep>

    result = "Null"

    # Now we need to convert our chosen forecast datetime
    # [2016-10-29T09:00:00Z] into date [2016-10-29Z] and mins [09*60]
    chosen_date = chosen_fcast_date_time[0:10] + 'Z'
    chosen_mins = int(chosen_fcast_date_time[11:13]) * 60
    HUB_LOGGER.info(
        "chosen_date %s chosen_mins %s",
        chosen_date,
        chosen_mins)

    # Now we can construct the xpath
    chosen_forecast_xpath = (
        "./DV/Location/Period[@type='Day' and "
        "@value='{0}']/Rep[text()='{1}']".format(
            chosen_date,
            chosen_mins))
    HUB_LOGGER.info("chosen_forecast_xpath %s", chosen_forecast_xpath)

    tgt_url = (
        'http://datapoint.metoffice.gov.uk/'
        'public/data/val/wxfcs/all/xml/{0}'.format(location_id))

    # then make the request
    req = requests.get(tgt_url, params=payload, timeout=20)
    HUB_LOGGER.info(
        "attempting to reach remote url: %s status: %s",
        req.url[:24],
        req.status_code)

    # find the result body - will be an xml document
    xml_result = req.content
    HUB_LOGGER.debug("meterology xml %s", xml_result)

    if xml_result.startswith("<?xml"):

        tree = etree.fromstring(req.content)
        latest_reports = tree.xpath(chosen_forecast_xpath)
        if latest_reports:
            latest_report = latest_reports[0]
            HUB_LOGGER.info("Latest Report: %s", latest_report)
            timestamp = latest_report.text
            fcast_value = latest_report.get(measurand)
            param_xpath = (
                "./Wx/Param[@name='{0}']".format(measurand))
            wx_param = tree.find(param_xpath)
            units = wx_param.get("units")
            measurand = wx_param.text

            HUB_LOGGER.info(
                "Chosen Forecast at %s %s Value: %s %s",
                int(timestamp)/60,
                measurand,
                fcast_value,
                units)
            if units == 'compass':
                result = WIND_DIRECTIONS[fcast_value]
            else:
                result = float(fcast_value)
    return result
