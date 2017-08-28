#
# Home Automation Hub
# Script to process actuators
#
# pylint: disable=broad-except
#

"""Actuator Handling"""

import sys
import logging
import hub_connect
import actuator_helpers

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def print_actuators(actuators):

    """Print Actuators"""

    HUB_LOGGER.info("Actuators")
    for actuator in actuators:
        HUB_LOGGER.info(
            "\t%s %s %s %s %s %s %s %s",
            actuator.get('ActuatorID'),
            actuator.get('CurrentValue'),
            '' if actuator.get('LastUpdated') is None else
            actuator.get('LastUpdated').strftime("%d-%m-%Y %H:%M:%S"),
            actuator.get('ActuatorTypeName'),
            actuator.get('ActuatorTypeDescription'),
            actuator.get('IsInAuto'),
            actuator.get('OffForMins'),
            actuator.get('OnForMins'))


def process_actuators():

    """Process Actuators"""

    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)
        select_sql = 'SELECT * from "vwActuators" ORDER BY "ActuatorID"'
        cur.execute(select_sql)
        # Read results into python list
        actuators = cur.fetchall()
        # uncomment to debug
        # print_actuators(actuators)
        HUB_LOGGER.debug("Processing actuators...")
        for actuator in actuators:
            actuator_id = int(actuator.get('ActuatorID'))
            HUB_LOGGER.debug("Processing actuator %d", actuator_id)
            cur_val = float(actuator.get('CurrentValue'))
            # Use computed method for actuators for this version
            # Actuator Function may include additional parameters,
            # so pass them as list
            # e.g. remote.130.5
            # {function.subnet_host.id} => function([subnet_host, id])
            actuator_function = actuator.get('ActuatorFunction')
            if actuator_function is not None:
                method_params = actuator_function.split('.')
                HUB_LOGGER.debug("method_params: %s", method_params)
                method_to_call = method_params.pop(0)
                HUB_LOGGER.debug("method_to_call: %s", method_to_call)
                method_exists = hasattr(actuator_helpers, method_to_call)
                HUB_LOGGER.debug(
                    "%s method exists: %s",
                    method_to_call,
                    method_exists)
                if method_exists:
                    # method_to_call = 'SimpleOnOff'
                    method = getattr(actuator_helpers, method_to_call)
                    result = method(cur_val, method_params)
                    HUB_LOGGER.debug("%f => %s", cur_val, result)
                else:
                    HUB_LOGGER.error("Missing Function: %s", method_to_call)
            else:
                HUB_LOGGER.error(
                    "Missing Function Specification for Actuator: %s",
                    actuator_id)

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to process actuators %s %s %s",
                         sys.exc_info()[0],
                         sys.exc_info()[1],
                         sys.exc_info()[2].tb_lineno)
