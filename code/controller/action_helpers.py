#

# Home Automation Hub

# Helper Script to process different Action Types

#

# pylint: disable=too-many-arguments

# pylint: disable=broad-except

#



"""Action Helpers"""



import sys

import logging

import datetime

import hub_connect

import helpers



# --- Global Variables ---

HUB_LOGGER = logging.getLogger('HubLogger')





def action_actuator(state,

                    agent,

                    method_params,

                    email_recipient,

                    text_recipient):



    """this action is simply to set the actuator state to the incoming state"""



    try:

        actuator_id = int(method_params[0])



        new_state = state  # simple mapping

        HUB_LOGGER.info(

            "In action_actuator setting %s to %s",

            actuator_id,

            new_state)



        do_action(new_state,

                  actuator_id,

                  agent,

                  'action_actuator',

                  email_recipient,

                  text_recipient)

        return new_state



    except Exception:

        HUB_LOGGER.error("Unable to Action Actuator")

        etype = sys.exc_info()[0]

        value = sys.exc_info()[1]

        trace = sys.exc_info()[2]

        line = trace.tb_lineno

        HUB_LOGGER.error("%s %s %s", etype, value, line)





def stop_actuator(state,

                  agent,

                  method_params,

                  email_recipient,

                  text_recipient):



    """this action is simply to set the actuator state to off on rising edge

        and ignore falling edge"""



    try:

        actuator_id = int(method_params[0])



        if state:



            new_state = False

            HUB_LOGGER.info("In stop_actuator setting %s to %s",

                            actuator_id,

                            new_state)



            do_action(new_state,

                      actuator_id,

                      agent,

                      'stop_actuator',

                      email_recipient,

                      text_recipient)



            return new_state



        else:



            HUB_LOGGER.info(

                "In stop_actuator ignoring falling edge for %s",

                actuator_id)



    except Exception:

        HUB_LOGGER.error("Unable to Stop Actuator")

        etype = sys.exc_info()[0]

        value = sys.exc_info()[1]

        trace = sys.exc_info()[2]

        line = trace.tb_lineno

        HUB_LOGGER.error("%s %s %s", etype, value, line)





def toggle_actuator(state,

                    agent,

                    method_params,

                    email_recipient,

                    text_recipient):



    """this action is simply to set the actuator state

       to the negation of the incoming state"""



    try:

        actuator_id = int(method_params[0])



        # need to ignore incoming state and re-read the actuator state!



        # Establish database Connection

        conn = hub_connect.get_connection()

        # Create cursor - needed for any database operation

        cur = hub_connect.get_cursor(conn)



        select_sql = 'SELECT * from "Actuator" WHERE "ActuatorID" = %s'



        # Read result

        cur.execute(select_sql, (actuator_id,))

        actuator = cur.fetchone()



        if actuator is not None:

            cur_val = actuator.get('CurrentValue')

            HUB_LOGGER.info(

                "In toggle_actuator reading %s current value as %s",

                actuator_id, cur_val)

            state = (float(cur_val) > 0.0)

            HUB_LOGGER.info("In toggle_actuator reading state as %s", state)

            new_state = not state  # toggle

            HUB_LOGGER.info("In toggle_actuator using new state %s", new_state)



            do_action(new_state,

                      actuator_id,

                      agent,

                      'toggle_actuator',

                      email_recipient,

                      text_recipient)



        else:

            new_state = False



        return new_state



    except Exception:

        HUB_LOGGER.error(

            "Unable to Toggle Actuator %s %s %s",

            sys.exc_info()[0],

            sys.exc_info()[1],

            sys.exc_info()[2].tb_lineno)





def send_alert(state, agent, action,

               email_recipient, text_recipient, act_name):



    """Send Alert"""



    subject = 'Message from Hub ' + datetime.datetime.strftime(

        datetime.datetime.now(), '%Y-%m-%d %H:%M:%S')

    # Open a plain text file for reading.

    file_ptr = open('templates/' + action, 'rb')

    # Create a text/plain message

    body = file_ptr.read()

    file_ptr.close()

    # Substitute values into template

    body = body.replace('{ActuatorName}', act_name)

    body = body.replace('{Agent}', agent)

    body = body.replace('{State}', 'On' if state else 'Off')

    body = body.replace('{ActionTime}',

                        datetime.datetime.strftime(

                            datetime.datetime.now(),

                            '%H:%M %d/%m/%Y'))

    if email_recipient is not None and email_recipient != '':

        HUB_LOGGER.info("Email Alert: %s", action)

        helpers.send_email(body, subject, email_recipient)

    if text_recipient is not None and text_recipient != '':

        HUB_LOGGER.info("Text Alert: %s", action)

        helpers.send_text(body, text_recipient)





def do_action(state,

              actuator_id,

              agent,

              action,

              email_recipient,

              text_recipient):



    """Do the Action"""



    HUB_LOGGER.info("In do_action incoming action alerts %s %s %s %s %s",

                    state,

                    actuator_id,

                    action,

                    email_recipient,

                    text_recipient)



    try:

        # Establish database Connection

        conn = hub_connect.get_connection()

        # Create cursor - needed for any database operation

        cur = hub_connect.get_cursor(conn)



        # Update the Actuator only if Current Value has changed!

        upd_sql = ('UPDATE "Actuator" SET "CurrentValue" = %s,'

                   ' "LastUpdated" = CURRENT_TIMESTAMP,'

                   ' "UpdatedBy" = %s WHERE'

                   ' "ActuatorID" = %s AND "IsInAuto" = \'Y\''

                   ' AND "CurrentValue" <> %s;')

        cur.execute(upd_sql, (state*1, agent, actuator_id, state*1))

        rows_affected = cur.rowcount

        HUB_LOGGER.debug("Update Actuator qry: [ %s ] Rows Affected: %s",

                        cur.query,

                        rows_affected)



        conn.commit()



        if rows_affected > 0:



            # Look-up the Actuator's Name for the alerts

            read_sql = ('SELECT "ActuatorName" FROM "Actuator" '

                        'WHERE "ActuatorID" = %s')



            # Read result

            cur.execute(read_sql, (actuator_id,))

            actuator = cur.fetchone()



            act_name = 'UnNamed Actuator'

            if actuator is not None:

                act_name = actuator.get('ActuatorName')



            # Send Alerts?

            if ((email_recipient is not None and email_recipient != '') or

               (text_recipient is not None and text_recipient != '')):



                send_alert(state, agent, action,

                           email_recipient, text_recipient, act_name)



        # Close communication with the database

        cur.close()

        conn.close()



    except Exception:

        HUB_LOGGER.error(

            "Unable to Do Action %s %s %s",

            sys.exc_info()[0],

            sys.exc_info()[1],

            sys.exc_info()[2].tb_lineno)



    return True





def action_text(state, agent, method_params):



    """this action sends a text"""



    try:

        phone_num = method_params[0]



        HUB_LOGGER.info("In ActionHelpers.Text texting %s with %s by %s",

                        phone_num, state, agent)

        helpers.send_text('Action Text Message', phone_num)



        return True



    except Exception:

        HUB_LOGGER.error("Unable to Send Text Message")

        etype = sys.exc_info()[0]

        value = sys.exc_info()[1]

        trace = sys.exc_info()[2]

        line = trace.tb_lineno

        HUB_LOGGER.error("%s %s %s", etype, value, line)

