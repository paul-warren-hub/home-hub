#
# Home Automation Hub
# Script to process rules
#
# Catching too general exception
# pylint: disable=W0703
#

"""Rules Engine"""

import sys
import logging
import hub_connect
import action_helpers

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def print_rules(rules):

    """Print Rules"""

    HUB_LOGGER.info("Rules")
    for rule in rules:
        HUB_LOGGER.info(
            "\t%d %s %s %s %s %d %s",
            rule.get('RuleID'),
            rule.get('RuleName'),
            rule.get('RuleDescription'),
            rule.get('SourceType'),
            rule.get('SourceID'),
            rule.get('ActionID'),
            "'" + rule.get('ActionFunction') + "'")


def process_rules_from_event_queue(source_type='*', source_id=-1):

    """Process Rules from Event Queue"""

    HUB_LOGGER.debug(
        "Processing Rules From Event Queue for %s %s...",
        source_type,
        source_id)
    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)
        event_queue_sql = "SELECT * from \"vwEventRuleActions\""
        if source_type != '*' and source_id != -1:
            # Request for specific rules to be processed
            event_queue_sql += (
                ' WHERE "SourceType" = \'' +
                source_type +
                '\' AND "SourceID" = ' + str(source_id))
            HUB_LOGGER.info(
                "Processing Rules From Event Queue sql: %s",
                event_queue_sql)
        cur.execute(event_queue_sql)
        # Read results into python list
        event_rule_actions = cur.fetchall()
        era_count = cur.rowcount

        if era_count > 0:

            HUB_LOGGER.info("Processing %s event rule actions...", era_count)

            for event_rule_action in event_rule_actions:

                process_event_rule_action(conn, cur, event_rule_action)

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to process rules")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)


def process_event_rule_action(conn, cur, event_rule_action):

    """Process Event Rule Action"""

    event_id = int(event_rule_action.get('EventID'))
    state = int(event_rule_action.get('Value'))
    agent = event_rule_action.get('SourceAgent')
    HUB_LOGGER.info("Processing event %d", event_id)
    # Process The Rules for this event
    action_function = event_rule_action.get('ActionFunction')
    email_recipient = event_rule_action.get('EmailRecipient')
    text_recipient = event_rule_action.get('TextRecipient')
    HUB_LOGGER.info("alert states: %s %s", email_recipient, text_recipient)
    method_params = action_function.split('.')
    HUB_LOGGER.info("method_params: %s", method_params)
    method_to_call = method_params.pop(0)
    HUB_LOGGER.info("method_to_call: %s", method_to_call)
    method = getattr(action_helpers, method_to_call)
    result = method(
        state,
        agent,
        method_params,
        email_recipient,
        text_recipient)
    HUB_LOGGER.info("%s => %s", method_to_call, result)
    # Mark Event As processed
    cur.execute(
        'UPDATE "EventQueue" SET "Processed" = true'
        ' WHERE "EventID" = %s;',
        (event_id, ))
    conn.commit()
    HUB_LOGGER.info("Event %s Successfully Processed", event_id)
