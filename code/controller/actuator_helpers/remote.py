#
# Home Automation Hub
# Helper Script to process remote Actuator
#
# pylint: disable=broad-except
#

"""Remote Actuator Helper"""

import sys
import logging
import helpers
import requests


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def remote(cur_val, method_params):

    """Remote Actuation"""

    try:

        subnet_host = method_params[0]
        actuator_id = int(method_params[1])
        HUB_LOGGER.info(
            "In remote %s %s %s ...",
            cur_val,
            subnet_host,
            actuator_id)
        status = -1

        # calculate the target url
        tgt_url = helpers.get_remote_url(
            'http://{0}/api.php',
            subnet_host)

        # set up request post parameters
        payload = {
            'actuator': actuator_id,
            'state': cur_val,
            'updatedby': 'master'}

        # then make the request
        req = requests.post(tgt_url, data=payload)
        HUB_LOGGER.info(
            "In remote POST response: %s http_status: %s",
            req.text,
            req.status_code)

        status = (req.status_code == 200)
        return status

    except Exception:
        HUB_LOGGER.error("Error in remote")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)
