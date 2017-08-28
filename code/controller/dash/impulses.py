#
# Home Automation Hub
# Script to manage impulses
#
# pylint: disable=broad-except
#

"""Impulse Management"""

import sys
import logging
import hub_connect
import RPi.GPIO as GPIO

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')
LOCKOUT_SECONDS = 10


def print_impulses(impulses):

    """Print Impulses"""

    HUB_LOGGER.info("Impulses")

    for impulse in impulses:
        HUB_LOGGER.info("   %s %s %s %s %s", impulse.get('ImpulseID'),
                        impulse.get('ImpulseName'),
                        impulse.get('ImpulseDescription'),
                        '' if impulse.get('LastUpdated') is None else
                        impulse.get('LastUpdated')
                        .strftime("%d-%m-%Y %H:%M:%S"),
                        impulse.get('BCMPinNumber'))


def initialise(impulse_pins):

    """Initialise"""

    try:

        HUB_LOGGER.info("Initialising impulses wired to physical buttons...")

        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)

        select_sql = (
            'SELECT * from "Impulse" WHERE "BCMPinNumber" IS NOT NULL'
            ' ORDER BY "ImpulseID"')
        cur.execute(select_sql)

        # Read results into python list
        impulses = cur.fetchall()
        print_impulses(impulses)

        GPIO.setmode(GPIO.BCM)

        cur_impulse_pins = []

        for impulse in impulses:

            impulse_id = int(impulse.get('ImpulseID'))
            pin = int(impulse.get('BCMPinNumber'))
            cur_impulse_pins.append(pin)

            if pin not in impulse_pins:

                # Impulse Switch wired from Pin to Ground
                # Add Pull-Up
                GPIO.setup(pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)

                # Clear any existing
                GPIO.remove_event_detect(pin)
                GPIO.add_event_detect(pin,
                                      GPIO.RISING,
                                      callback=invoke_callback,
                                      bouncetime=2000)

                # Mark Impulse as Initialised
                impulse_pins.append(pin)

                HUB_LOGGER.info("Initialised impulse %s on pin %s",
                                impulse_id, pin)

        ex_impulse_pins = set(impulse_pins) - set(cur_impulse_pins)

        for pin in ex_impulse_pins:
            # Clear any existing
            GPIO.remove_event_detect(pin)
            HUB_LOGGER.info("Decommissioned impulse on pin %s", pin)
            impulse_pins.remove(pin)

    except Exception:
        HUB_LOGGER.error("Unable to initialise impulses")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)


def arp_detected(mac):

    """ARP packet detected"""

    HUB_LOGGER.debug("ARP Request Detected : %s", mac)

    # Query Impulse for this mac address
    # Establish database Connection
    conn = hub_connect.get_connection()
    # Create cursor - needed for any database operation
    cur = hub_connect.get_cursor(conn)
    select_sql = 'SELECT * from "Impulse" WHERE "MacAddress" = %s'
    cur.execute(select_sql, (mac, ))

    # Read result
    impulse = cur.fetchone()

    if cur.rowcount == 1:

        HUB_LOGGER.info("Dash ARP Request Detected for %s", mac)

        create_event(conn, cur, impulse)


def invoke_callback(channel):

    """Invoke Callback"""

    try:

        HUB_LOGGER.info("Impulse Callback from pin %s", channel)

        # Only one impulse per pin allowed,
        #     so we can lookup the impulse from pin number

        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)

        select_sql = 'SELECT * from "Impulse" WHERE "BCMPinNumber" = %s'
        cur.execute(select_sql, (channel, ))

        # Read result
        impulse = cur.fetchone()

        if cur.rowcount == 1:

            HUB_LOGGER.info("Impulse Button Detected for pin %s", channel)

            create_event(conn, cur, impulse)

    except Exception:
        HUB_LOGGER.error("Unable to invoke impulse callback")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)


def create_event(conn, cur, impulse):

    """Create event"""

    try:

        impulse_id = impulse.get('ImpulseID')

        HUB_LOGGER.info("Inserting Impulse Event in Database... %s",
                        impulse_id)
        ins_sql = ('INSERT INTO "EventQueue" '
                   '("SourceID", "SourceType", "SourceAgent", "Value")'
                   'SELECT %s, \'Impulse\', \'Occupant\', 1'
                   'WHERE ('
                   '    SELECT COUNT(*)'
                   '    FROM "EventQueue"'
                   '    WHERE "SourceID" = %s AND'
                   '          "SourceType" = \'Impulse\''
                   '    AND "Timestamp" > '
                   '    current_timestamp - interval \'%s seconds\''
                   ') = 0;')
        cur.execute(ins_sql, (impulse_id, impulse_id, LOCKOUT_SECONDS))
        conn.commit()
        HUB_LOGGER.debug("Impulse Update Query... %s",
                        cur.query)
        HUB_LOGGER.info("Updating Impulse Last Updated in Database... %s",
                        impulse_id)
        cur.execute('UPDATE "Impulse" SET "LastUpdated" = CURRENT_TIMESTAMP'
                    ' WHERE "ImpulseID" = %s;', (impulse_id, ))
        conn.commit()

    except Exception:
        HUB_LOGGER.error("Unable to create event")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)
