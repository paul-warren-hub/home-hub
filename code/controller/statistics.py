#
# Home Automation Hub
# Script to calculate long term statistics from current values
#
# pylint: disable=broad-except
#

"""Hub Statistics"""

import sys
import traceback
import logging
import datetime
import hub_connect
import helpers


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def process_stats():

    """Process Statistics"""

    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)
        select_sql = (
            'SELECT * from "Sensor" s'
            ' INNER JOIN "Measurand" m ON s."MeasurandID" = m."MeasurandID"'
            ' ORDER BY "SensorID"')
        cur.execute(select_sql)
        # Read results into python list
        sensors = cur.fetchall()
        HUB_LOGGER.info("Processing Stats")
        for sensor in sensors:

            sensor_entry_id = int(sensor.get('SensorEntryID'))
            cur_val = float(sensor.get('CurrentValue'))
            meas_max_val = sensor.get('MaxValue')
            meas_min_val = sensor.get('MinValue')

            if meas_max_val is None or meas_max_val >= cur_val:
                HUB_LOGGER.info(
                    "Processing Max Stats for sensor %s", sensor_entry_id)
                process_max_min(conn, cur, sensor_entry_id, cur_val, 'Maximum')
            if meas_min_val is None or meas_min_val <= cur_val:
                HUB_LOGGER.info(
                    "Processing Min Stats for sensor %s", sensor_entry_id)
                process_max_min(conn, cur, sensor_entry_id, cur_val, 'Minimum')

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to process statistics")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        trace = traceback.format_exc()
        HUB_LOGGER.error("%s %s %s %s", etype, value, line, trace)


def process_max_min(conn, cur, sensor_entry_id, cur_val, stats_function):

    """Process Max or Min"""

    # Read current extremity
    sel_sql = (
        'SELECT * from "Statistic"'
        ' WHERE "SensorEntryID" = %s AND "StatsFunction" = %s')
    cur.execute(sel_sql, (sensor_entry_id, stats_function))
    # Read results into python list
    stats = cur.fetchall()
    row_count = cur.rowcount
    if row_count == 0:
        # Insert Database with statistic
        HUB_LOGGER.info(
            "New %s Statistic Sensor %s Value %s",
            stats_function, sensor_entry_id, cur_val)
        ins_sql = (
            'INSERT INTO "Statistic"'
            ' ("SensorEntryID", "Value", "Timestamp", "StatsFunction")'
            ' VALUES (%s, %s, CURRENT_TIMESTAMP, %s);')
        cur.execute(ins_sql, (sensor_entry_id, cur_val, stats_function))
        conn.commit()
    else:
        extremity = stats[0]
        extreme_val = float(extremity.get('Value'))
        # Update Database with statistic?
        if (stats_function == 'Maximum' and cur_val > extreme_val or
                stats_function == 'Minimum' and cur_val < extreme_val):
            HUB_LOGGER.info(
                "Updated %s Statistic Sensor %s"
                " Old Value %s, New Value %s",
                stats_function, sensor_entry_id, cur_val, extreme_val)
            upd_sql = (
                'UPDATE "Statistic"'
                ' SET "Value" = %s, "Timestamp" = CURRENT_TIMESTAMP'
                ' WHERE "SensorEntryID" = %s'
                ' AND "StatsFunction" = %s')
            cur.execute(upd_sql, (cur_val, sensor_entry_id, stats_function))
            conn.commit()


def daily_summary():

    """Daily Summary"""

    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)
        select_sql = 'SELECT * from "vwTodaysMaximums" ORDER BY "SensorID"'
        cur.execute(select_sql)
        msg_body = ''
        msg_tmplt = '* {0} {1} {2} at {3}\r\n'
        if cur.rowcount > 0:
            # Read results into python list
            maximums = cur.fetchall()
            HUB_LOGGER.info("Processing Daily Maxs")
            msg_body += (
                'The following highest values'
                ' have been recorded today:\r\n\r\n')
            for maximum in maximums:
                sensor_title = maximum.get('SensorTitle')
                val = float(maximum.get('Value'))
                units = maximum.get('TextUnits')
                date = datetime.datetime.strftime(
                    maximum.get('Timestamp'), '%H:%M %d/%m/%Y')
                msg_body += msg_tmplt.format(sensor_title, val, units, date)
            msg_body += '\r\n'
        select_sql = "SELECT * from \"vwTodaysMinimums\" ORDER BY \"SensorID\""
        cur.execute(select_sql)
        if cur.rowcount > 0:
            # Read results into python list
            minimums = cur.fetchall()
            HUB_LOGGER.info("Processing Daily Mins")
            msg_body += (
                'The following lowest values'
                ' have been recorded today:\r\n\r\n')
            for minimum in minimums:
                sensor_title = minimum.get('SensorTitle')
                val = float(minimum.get('Value'))
                units = minimum.get('TextUnits')
                date = datetime.datetime.strftime(
                    minimum.get('Timestamp'), '%H:%M %d/%m/%Y')
                msg_body += msg_tmplt.format(sensor_title, val, units, date)
        if msg_body != '':

            HUB_LOGGER.info("Processing Daily Summary: %s", msg_body)
            # Send Email
            subject = (
                'Daily Highs and Lows from the Hub ' +
                datetime.datetime.strftime(
                    datetime.datetime.now(), '%Y-%m-%d'))
            email_recip = helpers.get_user_setting('AdminRecipient')
            helpers.send_email(msg_body, subject, email_recip)

    except Exception:
        HUB_LOGGER.error("Unable to process daily summary statistics")
        HUB_LOGGER.error(
            "%s %s %s",
            sys.exc_info()[0],
            sys.exc_info()[1],
            sys.exc_info()[2].tb_lineno)
