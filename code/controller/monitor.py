#
# Home Automation Hub
# Script to check conditions
#
# pylint: disable=broad-except
# pylint: disable=eval-used
#

"""Monitor for Conditions"""

import sys
import datetime
import logging
import hub_connect

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def print_conditions(conditions):

    """Print Conditions"""

    HUB_LOGGER.info("Conditions")
    for condition in conditions:
        HUB_LOGGER.info(
            "\t%s %s %s %s %s %s %s %s",
            condition.get('ConditionID'),
            condition.get('ConditionName'),
            condition.get('ConditionDescription'),
            condition.get('CurrentCondition'),
            condition.get('SetOperator'),
            condition.get('SetThreshold'),
            condition.get('ResetOperator'),
            condition.get('ResetThreshold'))


def print_complex_conditions(conditions):

    """Print Complex Conditions"""

    HUB_LOGGER.info("Complex Conditions")
    for condition in conditions:
        HUB_LOGGER.info(
            "\t%s %s %s %s %s %s",
            condition.get('ConditionID'),
            condition.get('ConditionName'),
            condition.get('ConditionDescription'),
            condition.get('CurrentCondition'),
            condition.get('SetExpression'),
            condition.get('ResetExpression'))


def check_complex_conditions():

    """Check Complex Conditions"""

    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)

        select_sql = (
            'SELECT * from "vwComplexConditions" '
            'ORDER BY "ConditionID"')
        cur.execute(select_sql)

        # Read results into python list
        conditions = cur.fetchall()
        print_complex_conditions(conditions)

        select_sql = (
            'SELECT "SensorID", "CurrentValue" FROM "Sensor" '
            'ORDER BY "SensorID" DESC')
        cur.execute(select_sql)

        # Read results into python list
        cur_vals = cur.fetchall()

        select_sql = ('SELECT "ActuatorID", "OnForMins", "OffForMins" '
                      'FROM "Actuator"')
        cur.execute(select_sql)

        # Read results into python list
        cur_timers = cur.fetchall()

        HUB_LOGGER.info("Processing Complex Conditions...")
        for condition in conditions:

            HUB_LOGGER.info("-------------------------------")
            check_condition(conn, cur, cur_vals, cur_timers, condition)

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to process complex conditions")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)


def check_condition(conn, cur, cur_vals, cur_timers, condition):

    """Check Condition"""

    condition_id = int(condition.get('ConditionID'))
    cur_cond = bool(condition.get('CurrentCondition'))
    HUB_LOGGER.info("Current Condition: %s", cur_cond)

    ins_evt_sql = (
            'INSERT INTO "EventQueue" '
            '( "SourceID", "SourceType", "SourceAgent", "Value") '
            'VALUES (%s, %s, %s, %s);')

    upd_cond_sql = (
            'UPDATE "Condition" SET "CurrentValue" = %s,'
            ' "LastUpdated" = CURRENT_TIMESTAMP,'
            ' "ErrorMessage" = NULL WHERE "ConditionID" = %s;')

    exc_err_state, exc_result = check_exception_condition(
        conn, cur, cur_vals, cur_timers, condition)

    norm_err_state, norm_result = check_normal_condition(
        conn, cur, cur_vals, cur_timers, condition)
    err_state = exc_err_state or norm_err_state
    if not err_state and (exc_result and not cur_cond):

        # Exceptional
        HUB_LOGGER.info("Exceptional...")
        new_condition = True

        # Insert Event into Database
        HUB_LOGGER.info("Inserting Exception Event in Queue...")
        cur.execute(ins_evt_sql, (condition_id, "Condition", "Hub", 1))
        HUB_LOGGER.info("new_condition: %s", new_condition)

        # Update Condition with current boolean state
        cur.execute(upd_cond_sql, (new_condition, condition_id))
        conn.commit()

    elif not err_state and (norm_result and cur_cond):

        # Normal
        HUB_LOGGER.info("Normal...")
        new_condition = False

        # Insert Event into Database
        HUB_LOGGER.info("Inserting Normal Event in Queue...")
        cur.execute(ins_evt_sql, (condition_id, "Condition", "Hub", 0))
        HUB_LOGGER.info("new_condition: %s", new_condition)

        # Update Condition with current boolean state
        cur.execute(upd_cond_sql, (new_condition, condition_id))
        conn.commit()

    elif not err_state:

        # No Change
        HUB_LOGGER.info("Inserting No Events in Queue...")

        # Update Condition with cleared error state
        cur.execute(
            'UPDATE "Condition" SET "LastUpdated" = CURRENT_TIMESTAMP,'
            ' "ErrorMessage" = NULL WHERE "ConditionID" = %s;',
            (condition_id, ))
        conn.commit()
        new_condition = cur_cond

    else:

        HUB_LOGGER.info(
            "Inserting No Events in Queue due to condition error")
        new_condition = cur_cond  # No Change - Error


def check_exception_condition(conn, cur, cur_vals,
                              cur_timers, condition):

    """Check Exception Condition"""

    condition_id = int(condition.get('ConditionID'))
    set_expr = condition.get('SetExpression')

    exception_conditional = parse_condition_expression(
        set_expr,
        cur_vals,
        cur_timers)

    HUB_LOGGER.info(
        "Exception Conditional Expression: %s",
        exception_conditional)

    exc_result = -99  # Assume Worst
    err_state = True
    try:
        exc_result = eval(exception_conditional)
        err_state = False
        HUB_LOGGER.info("%s => %s", exception_conditional, exc_result)
    except Exception:
        err_msg = "Set: " + str(sys.exc_info()[1])

        HUB_LOGGER.error(
            "ERROR Evaluating Exception Condition: '%s' %s",
            exception_conditional,
            err_msg)

        # Update Condition with error state
        cur.execute(
            'UPDATE "Condition" SET "ErrorMessage" = %s,'
            ' "LastUpdated" = CURRENT_TIMESTAMP'
            ' WHERE "ConditionID" = %s;',
            (err_msg, condition_id))

        conn.commit()

    return err_state, exc_result


def check_normal_condition(conn, cur, cur_vals, cur_timers, condition):

    """Check Normal Condition"""

    condition_id = int(condition.get('ConditionID'))
    reset_expr = condition.get('ResetExpression')

    normal_conditional = parse_condition_expression(
        reset_expr,
        cur_vals,
        cur_timers)

    HUB_LOGGER.info("Normal Conditional Expression: %s", normal_conditional)

    err_state = True
    norm_result = -99  # Assume worst
    try:
        norm_result = eval(normal_conditional)
        err_state = False
        HUB_LOGGER.info("%s => %s", normal_conditional, norm_result)
    except Exception:
        err_msg = "Reset: " + str(sys.exc_info()[1])
        HUB_LOGGER.error(
            "ERROR Evaluating Normal Condition: '%s' %s",
            normal_conditional,
            err_msg)
        # Update Condition with error state
        cur.execute(
            'UPDATE "Condition" SET "ErrorMessage" = %s,'
            ' "LastUpdated" = CURRENT_TIMESTAMP'
            ' WHERE "ConditionID" = %s;',
            (err_msg, condition_id))
        conn.commit()

    return err_state, norm_result


def parse_condition_expression(expr, cur_vals, cur_timers):

    """Parse Condition Expression"""

    # use reverse ordered list of sensor values
    # to substitute for S1, S2 values in expression

    parsed = expr
    HUB_LOGGER.info("Expression to parse: %s", parsed)
    for cur_val in cur_vals:

        parsed = parse_sensor(parsed, cur_val)

    # Maybe a Time Expression: TOD (Time of Day),
    # TOWD (Time of WeekDay), TOWE (Time of WeekEnd)
    null_time = "'..:..'"  # this can never exceed a set time
    day_no = datetime.datetime.today().weekday()
    is_week_day = (day_no < 5)
    is_week_end = (day_no >= 5)
    time_of_day = datetime.datetime.today().strftime("'%H:%M'")
    parsed = parsed.replace("TOD", time_of_day)
    if is_week_day:
        parsed = parsed.replace("TOWD", time_of_day)
    else:
        parsed = parsed.replace("TOWD", null_time)
    if is_week_end:
        parsed = parsed.replace("TOWE", time_of_day)
    else:
        parsed = parsed.replace("TOWE", null_time)

    HUB_LOGGER.debug("parsed expression: %s", parsed)

    # Maybe a Timer - T2, T3, etc
    # use reverse ordered list of timer values
    # to substitute for T1, T2, t1, t2 values in expression

    for cur_timer in cur_timers:
        tim_id = cur_timer.get('ActuatorID')
        value = str(cur_timer.get('OnForMins'))
        place_holder = 'T' + str(tim_id)
        parsed = parsed.replace(place_holder, value)

    for cur_timer in cur_timers:
        tim_id = cur_timer.get('ActuatorID')
        value = str(cur_timer.get('OffForMins'))
        place_holder = 't' + str(tim_id)
        parsed = parsed.replace(place_holder, value)

    return parsed


def parse_sensor(parsed, cur_val):

    """Parse Sensor"""

    sens_id = cur_val.get('SensorID')
    HUB_LOGGER.debug("Sensor ID: %s", sens_id)
    value = str(cur_val.get('CurrentValue'))
    HUB_LOGGER.debug("Value: %s", value)
    place_holder = 'S' + str(sens_id)
    HUB_LOGGER.debug("place_holder: %s", place_holder)
    parsed = parsed.replace(place_holder, value)
    return parsed


def truncate_samples():

    """Truncate Samples"""

    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)

        delete_sql1 = (
            'DELETE FROM "Sample" '
            'WHERE "Timestamp" < (NOW() - INTERVAL \'1 WEEK\')')
        HUB_LOGGER.info("Truncating Sample Table...")
        cur.execute(delete_sql1)
        conn.commit()
        delete_sql2 = (
            'DELETE FROM "EventQueue" '
            'WHERE "Timestamp" < (NOW() - INTERVAL \'1 WEEK\')')
        HUB_LOGGER.info("Truncating EventQueue Table...")
        cur.execute(delete_sql2)
        conn.commit()

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to truncate samples")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)
