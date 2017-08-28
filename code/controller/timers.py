#
# Home Automation Hub
# Script to process timers
# pylint: disable=broad-except
#

"""Timer Processing"""

import sys
import logging
import hub_connect


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def print_timers(timers):

    """Print Timers"""

    HUB_LOGGER.info("Timers")
    for timer in timers:
        HUB_LOGGER.info(
            "\t%d %s %d %d",
            timer.get('TimerID'),
            timer.get('TimerName'),
            timer.get('TimerDefaultMins'),
            timer.get('TimerCurrentMins'))


def process_timers():

    """Processes Timers"""

    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)

        # Process Actuator Timers
        select_sql = (
            'SELECT "ActuatorEntryID", "OnForMins", "OffForMins" '
            'FROM "Actuator"')
        cur.execute(select_sql)
        # Read results into python list
        actuators = cur.fetchall()
        HUB_LOGGER.info("Processing Actuator Timers")
        for act in actuators:
            act_id = int(act.get('ActuatorEntryID'))
            cur_on_val = int(act.get('OnForMins'))
            if cur_on_val >= 0:
                HUB_LOGGER.debug("Incrementing Actuator On Timer %s", act_id)
                cur_on_val = cur_on_val + 1
                # Update Database with current value
                cur.execute(
                    'UPDATE "Actuator" SET "OnForMins" = %s '
                    'WHERE "ActuatorEntryID" = %s;',
                    (cur_on_val, act_id))
                conn.commit()
            else:
                HUB_LOGGER.debug(
                    "Ignoring Actuator On Timer %s - Disabled",
                    act_id)

            cur_off_val = int(act.get('OffForMins'))
            if cur_off_val >= 0:
                HUB_LOGGER.debug("Incrementing Actuator Off Timer %s", act_id)
                cur_off_val = cur_off_val + 1
                # Update Database with current value
                cur.execute(
                    'UPDATE "Actuator" SET "OffForMins" = %s '
                    'WHERE "ActuatorEntryID" = %s;',
                    (cur_off_val, act_id))
                conn.commit()
            else:
                HUB_LOGGER.debug(
                    "Ignoring Actuator Off Timer %s - Disabled",
                    act_id)

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to process timers")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)
