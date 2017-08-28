#
# Home Automation Hub
# Script to process current values
#
# Catching too general exception
# pylint: disable=W0703
#

"""Read Current Values."""

import sys
import traceback
import math
import logging
import datetime
import hub_connect
import sensor_helpers
import helpers


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')
ALERT_COUNT_INIT = 3


def print_sensors(sensors):

    """Print Sensor Values."""

    HUB_LOGGER.info("Sensors")

    for sensor in sensors:
        last_updated = sensor.get('LastUpdated')
        fmt = "%d-%m-%Y %H:%M:%S"
        HUB_LOGGER.info(
            "%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s",
            sensor.get('SensorID'),
            sensor.get('CurrentValue'),
            '' if last_updated is None else last_updated.strftime(fmt),
            sensor.get('MeasurandID'), sensor.get('MeasurandName'),
            sensor.get('SensorFunction'), sensor.get('MinValue'),
            sensor.get('MaxValue'), sensor.get('MaxDelta'))


def process_current_values(alert_counts):

    """Process Sensor Values."""

    try:

        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)
        select_sql = 'SELECT * from "vwSensorsAndTypes" ORDER BY "SensorID"'
        cur.execute(select_sql)
        # Read results into python list
        sensors = cur.fetchall()
        print_sensors(sensors)
        HUB_LOGGER.info("Processing Sensors...")
        cur_vals = {}
        for sensor in sensors:

            process_sensor(cur, cur_vals, sensor, alert_counts)

        conn.commit()

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to process Current Values")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        line = sys.exc_info()[2].tb_lineno
        HUB_LOGGER.error("%s %s %s %s",
                         etype, value, line, traceback.format_exc())


def process_sensor(cur, cur_vals, sensor, alert_counts):

    """Process Sensor."""

    sensor_id = int(sensor.get('SensorID'))
    err_msg = 'No Sensor'
    err_val = -99.0
    cur_val = err_val
    cur_val_str = sensor.get('CurrentValue')
    if cur_val_str is not None:
        cur_val = float(cur_val_str)

    try:

        retry_count = 3
        valid_result = False  # assume worst

        while retry_count > 0 and not valid_result:

            HUB_LOGGER.info("In retry Loop %d", retry_count)

            result, timestamp = read_sensor(
                cur_vals, sensor, cur_val)

            HUB_LOGGER.info("raw result: %s", result)

            if result is not None and result != 'Null':

                result = round(result, 2)
                HUB_LOGGER.info("rounded result: %s", result)

                valid_result, err_msg, cur_val = validate_result(
                    sensor_id,
                    err_val,
                    cur_val,
                    result,
                    sensor)

            elif result == 'Null':
                # Null means ignore
                valid_result = True

            if not valid_result and result != 'Null':
                HUB_LOGGER.warning(
                    "Invalid Measurement Detected: %s", err_msg)

            retry_count -= 1

        if retry_count == 0:
            HUB_LOGGER.warning(
                "Sensor %s Failed 3 retries - don't update", sensor_id)

        HUB_LOGGER.info("%s => %s", cur_val, result)

    except Exception:
        # error in read retry loop
        HUB_LOGGER.error("Error in read retry loop")
        line = sys.exc_info()[2].tb_lineno
        HUB_LOGGER.error("%s %s %s",
                         sys.exc_info()[0], sys.exc_info()[1], line)

    if valid_result and result != 'Null':

        # Update Database with current value
        upd_qry = (
            'UPDATE "Sensor" SET "CurrentValue" = %s,'
            '"LastUpdated" = %s,'
            '"ErrorMessage" = NULL WHERE "SensorID" = %s;')
        cur.execute(upd_qry, (result, timestamp, sensor_id))

        # Check Alerts
        check_high_alerts(sensor, result, alert_counts)
        check_low_alerts(sensor, result, alert_counts)

        # Save current value in dictionary - list must be consecutive
        cur_vals[sensor_id] = result

    else:

        cur.execute('UPDATE "Sensor" '
                    'SET "ErrorMessage" = %s '
                    'WHERE "SensorID" = %s;',
                    (err_msg, sensor_id))  # Update Database with error message


def read_sensor(cur_vals, sensor, cur_val):

    '''Read Sensor'''

    result = -99.9

    # Sensor Function may have additional parameters, pass them as list
    # e.g. am2302.18.0 {function.pin.channel} => function([pin, channel])
    sensor_function = sensor.get('SensorFunction')
    method_params = sensor_function.split('.')
    HUB_LOGGER.info("method_params: %s", method_params)
    method_to_call = method_params.pop(0)
    HUB_LOGGER.info("method_to_call: %s", method_to_call)
    method_exists = hasattr(sensor_helpers, method_to_call)
    HUB_LOGGER.info("%s method exists: %s", method_to_call, method_exists)
    timestamp = datetime.datetime.now().isoformat()

    if not method_exists:
        HUB_LOGGER.info('No sensor helper method found - '
                        '%s must be an expression: ',
                        method_to_call)
        method = getattr(sensor_helpers, 'evaluate')
        result = method(sensor_function, cur_vals)[0]
        HUB_LOGGER.info("evaluated result: %s", result)

    else:
        method = getattr(sensor_helpers, method_to_call)
        # now for forecasted results we expect back a tuple...
        result_and_timestamp = method(cur_val, method_params)
        if result_and_timestamp[0] is not None:
            result = result_and_timestamp[0]
        if len(result_and_timestamp) == 2:
            timestamp = result_and_timestamp[1]
    return result, timestamp


def validate_result(sensor_id,
                    err_val,
                    cur_val,
                    result,
                    sensor):

    """Validate Result."""

    err_msg = ''
    max_val = sensor.get('MaxValue')
    min_val = sensor.get('MinValue')
    max_delta = sensor.get('MaxDelta')

    if max_val is None:
        over_range = False
    else:
        over_range = result > max_val
    if over_range:
        err_msg = "Over-Range: {0} > {1}".format(result, max_val)
    if min_val is None:
        under_range = False
    else:
        under_range = result < min_val
    if under_range:
        err_msg = "Under-Range: {0} < {1}".format(result, min_val)
    if max_delta is None:
        over_delta = False
    else:
        try:

            if cur_val == err_val:
                # need to reset sometimes
                cur_val = result
            diff = math.sqrt(
                math.pow(result - cur_val, 2))
            HUB_LOGGER.info("Difference %s", diff)

            delta = diff * 100 / float(max_val - min_val)
            HUB_LOGGER.info("Delta-percent %s", delta)

            over_delta = delta > max_delta

        except (TypeError, ValueError):
            HUB_LOGGER.info("Unable to calculate over-delta - assume O.K.")
            over_delta = False

    if over_delta and not (over_range or under_range):
        err_msg = "Over-Delta: {0:.2f}% > {1}%".format(delta, max_delta)
    HUB_LOGGER.info("Sensor: %s - Value: %s"
                    " Previous: %s over_range: %s"
                    " under_range: %s over_delta: %s",
                    sensor_id,
                    result,
                    cur_val,
                    over_range,
                    under_range,
                    over_delta)
    valid_result = not over_range or under_range or over_delta
    return valid_result, err_msg, cur_val


def check_high_alerts(sensor, result, alert_counts):

    """Check High Alerts."""

    sensor_id = sensor.get('SensorID')
    sensor_id_str = 'H' + str(sensor_id)

    high_alert = sensor.get('HighAlert')
    high_alert_triggered = False

    if high_alert is not None:

        # High alert detection
        if result > high_alert:
            if sensor_id_str not in alert_counts:
                # One-shot high alert init
                alert_counts[sensor_id_str] = ALERT_COUNT_INIT
                HUB_LOGGER.info(
                    'Sensor %s High Alert %s < %s Count Initialised to %s',
                    sensor_id, result, high_alert, ALERT_COUNT_INIT)
            else:
                alert_counts[sensor_id_str] -= 1
                if alert_counts[sensor_id_str] == 0:
                    high_alert_triggered = True
                    HUB_LOGGER.info(
                        'Sensor %s High Alert %s < %s Triggered',
                        sensor_id, result, high_alert)
                else:
                    HUB_LOGGER.info(
                        'Sensor %s High Alert %s < %s Count Decremented to %s',
                        sensor_id, result, high_alert,
                        alert_counts[sensor_id_str])
        elif sensor_id_str in alert_counts:
            HUB_LOGGER.info(
                'Sensor %s High Alert %s < %s Cancelled',
                sensor_id, result, high_alert)
            del alert_counts[sensor_id_str]

    if high_alert_triggered:
        HUB_LOGGER.info(
            'Sensor %s high alert exceeded - %s > %s',
            sensor_id, result, high_alert)
        sensor_title = sensor.get('SensorTitle')
        text_units = sensor.get('TextUnits')
        cur_time = datetime.datetime.strftime(
            datetime.datetime.now(),
            '%A %H:%M')
        msg_body_tmplt = '{0} high alert level exceeded: {1} > {2} {3} - {4}'
        msg_body = msg_body_tmplt.format(
            sensor_title,
            str(result),
            str(high_alert),
            text_units,
            cur_time)
        subject = 'Sensor high alert level exceeded'
        txt_recip = sensor.get('TextRecipient')
        email_recip = sensor.get('EmailRecipient')
        issue_alert(msg_body, subject, txt_recip, email_recip)


def check_low_alerts(sensor, result, alert_counts):

    """Check Low Alerts."""

    sensor_id = sensor.get('SensorID')
    sensor_id_str = 'L' + str(sensor_id)

    low_alert = sensor.get('LowAlert')
    low_alert_triggered = False

    if low_alert is not None:

        # Low alert detection
        if result < low_alert:
            if sensor_id_str not in alert_counts:
                # One-shot low alert init
                alert_counts[sensor_id_str] = ALERT_COUNT_INIT
                HUB_LOGGER.info(
                    'Sensor %s Low Alert %s < %s Count Initialised to %s',
                    sensor_id, result, low_alert, ALERT_COUNT_INIT)
            else:
                alert_counts[sensor_id_str] -= 1
                if alert_counts[sensor_id_str] == 0:
                    low_alert_triggered = True
                    HUB_LOGGER.info(
                        'Sensor %s Low Alert %s < %s Triggered',
                        sensor_id, result, low_alert)
                else:
                    HUB_LOGGER.info(
                        'Sensor %s Low Alert %s < %s Count Decremented to %s',
                        sensor_id, result, low_alert,
                        alert_counts[sensor_id_str])
        elif sensor_id_str in alert_counts:
            HUB_LOGGER.info(
                'Sensor %s Low Alert %s < %s Cancelled',
                sensor_id, result, low_alert)
            del alert_counts[sensor_id_str]

    if low_alert_triggered:
        HUB_LOGGER.info(
            'Sensor %s low alert exceeded - %s < %s',
            sensor_id, result, low_alert)
        sensor_title = sensor.get('SensorTitle')
        text_units = sensor.get('TextUnits')
        cur_time = datetime.datetime.strftime(
            datetime.datetime.now(),
            '%A %H:%M')
        msg_body_tmplt = (
            '{0} low alert level exceeded: {1} < {2} {3} - {4}')
        msg_body = msg_body_tmplt.format(
            sensor_title,
            str(result),
            str(low_alert),
            text_units,
            cur_time)
        subject = 'Sensor low alert level exceeded'
        txt_recip = sensor.get('TextRecipient')
        email_recip = sensor.get('EmailRecipient')
        issue_alert(msg_body, subject, txt_recip, email_recip)


def issue_alert(msg_body, subject, txt_recip, email_recip):

    """Issue Alert"""

    if txt_recip is not None:
        helpers.send_text(msg_body, txt_recip)
    if email_recip is not None:
        helpers.send_email(msg_body, subject, email_recip)
