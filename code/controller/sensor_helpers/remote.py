#
# Home Automation Hub
# Helper Script to read remote sensor
#
# pylint: disable=broad-except
#

"""Remote Sensor Helper Function."""

import sys
import logging
import helpers
import json
import requests
import requests_cache


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def remote(cur_val, method_params):
    # remote.x.y
    try:
        subnet_host = method_params[0]
        sensor = method_params[1]
        HUB_LOGGER.info("host: %s sensor: %s", subnet_host, sensor)

        # Calculate the target url
        tgt_url = helpers.get_remote_url(
            'http://{0}/api.php',
            subnet_host)

        # Set up request query string parameters
        payload = {'sensor': sensor}

        # Then make the request
        #
        #    API
        #   ===
        #    0 = success,
        #    -1 = request method error,
        #    -2 = no target defined error,
        #    -3 = db query error
        #    -4 = no data available error
        #
        #    LOCAL
        #    =====
        #    -10 = unreachable

        remote_status = -10
        try:

            # Make the uncached request - note req.from_cache is not available!
            with requests_cache.disabled():
                req = requests.get(tgt_url, params=payload, timeout=20)

            # Log the request url
            HUB_LOGGER.info("attempting to reach remote url: %s", req.url)

            # find the result body - will be a json object
            # {
            #    "results": [{
            #        "id": 55,
            #        "value": 10.00,
            #        "updated": "2016-10-30 09:00:00"
            #    }, {
            #        "id": 56,
            #        "value": 12.00,
            #        "updated": "2016-10-30 09:00:00"
            #    }],
            #    "status": 0
            # }

            json_result = req.text
            HUB_LOGGER.info("remote json result: %s", json_result)

            # Convert to Python
            python_data = json.loads(json_result)
            remote_value = python_data["results"][0]["value"]
            remote_date = python_data["results"][0]["updated"]
            remote_status = python_data["status"]
            HUB_LOGGER.info(
                "remote values: [ Value: %s , Date: %s , Status: %s ]",
                remote_value,
                remote_date,
                remote_status)

        except Exception:
            HUB_LOGGER.warning("remote node unreachable: %s",
                               tgt_url)
            etype = sys.exc_info()[0]
            value = sys.exc_info()[1]
            trace = sys.exc_info()[2]
            line = trace.tb_lineno
            HUB_LOGGER.error("%s %s %s", etype, value, line)

        # Check status to see if result is valid
        if remote_status == 0:
            HUB_LOGGER.info(
                "remote fetch successful - Value: %s ",
                remote_value)
            result = remote_value
            timestamp = remote_date
        else:
            HUB_LOGGER.info(
                "remote fetch failed - "
                "Status: %s setting result value to none",
                remote_status)
            result = None
            timestamp = None

        # Return result and optional timestamp
        return (result, timestamp)

    except Exception:
        HUB_LOGGER.error("Unable to process remote sensor")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)
