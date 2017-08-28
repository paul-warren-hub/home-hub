#
# Home Automation Hub
# Main Script to coordinate activities
#
# pylint: disable=broad-except
# pylint: disable=global-statement
# pylint: disable=invalid-name
#


"""Main Scheduler."""

import sys
import traceback
import os
import datetime
import time
import threading
import signal
import logging.handlers
import socket
import struct
import binascii
import statistics
import helpers
import current_values
import sampler
import actuators
import timers
import monitor
import rules_engine
import impulses


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')
LOG_FILENAME = '/var/log/hub/hub.log'
_loop_counter = -1


# Routine that gets called on shutdown
def shutdown_scheduler():

    """Shutdown."""

    global _loop_counter
    HUB_LOGGER.warn('SIGTERM Shutting Down...')
    _loop_counter = 0


# Routine that processes network monitoring tasks in background
def network_monitor():

    """Network monitor."""

    HUB_LOGGER.debug(
        "Doing Network Monitoring...")

    global _loop_counter

    rawSocket = socket.socket(socket.AF_PACKET,
                              socket.SOCK_RAW,
                              socket.htons(0x0003))

    while _loop_counter != 0:

        packet = rawSocket.recvfrom(2048)

        ethernet_header = packet[0][0:14]
        ethernet_detailed = struct.unpack("!6s6s2s", ethernet_header)

        arp_header = packet[0][14:42]
        arp_detailed = struct.unpack("2s2s1s1s2s6s4s6s4s", arp_header)

        ethertype = ethernet_detailed[2]
        opcode = arp_detailed[4]
        if ethertype == '\x08\x00' and opcode == '\x00\x00':
            mac_address = (
                ':'.join(s.encode('hex')
                         for s in binascii.hexlify(ethernet_detailed[1]).
                         decode('hex')))
            HUB_LOGGER.debug("Source MAC: %s", mac_address)
            impulses.arp_detected(mac_address)

    HUB_LOGGER.warn("Network Monitor closed")


# Routine that processes high priority tasks in background
def priority_work():

    """Priority work."""

    global _loop_counter
    while _loop_counter != 0:
        HUB_LOGGER.debug(
            "Doing Priority Work... _loop_counter: %s",
            _loop_counter)
        rules_engine.process_rules_from_event_queue()
        actuators.process_actuators()
        time.sleep(1)

    HUB_LOGGER.warn("Priority Work closed")


# Routine that processes time-sensitive tasks in background
def time_sensitive_work():

    """Time sensitive work."""

    global _loop_counter

    while _loop_counter != 0:

        # Wait for boundary...
        secs = -1
        while secs != 55:
            time.sleep(0.5)
            secs = datetime.datetime.now().second

        # Process Minute-resolution timers
        timers.process_timers()
        time.sleep(1)

    HUB_LOGGER.warn("Time Sensitive Work closed")


def init_logging(log_level):

    """ Initialise Logging """

    # assuming log_level is bound to the string value
    # obtained from the command line argument.
    numeric_log_level = getattr(logging, log_level.upper(), None)
    log_directory = os.path.dirname(LOG_FILENAME)
    if not os.path.exists(log_directory):
        os.makedirs(log_directory)
    if not isinstance(numeric_log_level, int):
        raise ValueError('Invalid log level: %s' % log_level)
    HUB_LOGGER.setLevel(numeric_log_level)
    # Add the log message handler to the logger
    log_handler = logging.handlers.RotatingFileHandler(
        LOG_FILENAME, maxBytes=5000000, backupCount=20)
    # create formatter
    log_formatter = logging.Formatter(
        "%(asctime)s %(levelname)s %(module)s - %(funcName)s: %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S")
    # add formatter to handler
    log_handler.setFormatter(log_formatter)
    HUB_LOGGER.addHandler(log_handler)


def main():

    """Main Loop."""

    global _loop_counter
    try:
        # Read Arguments
        # specify --log=DEBUG or --log=debug, slave=false
        print sys.argv
        log_level = sys.argv[1].split("=")[1]
        slave = bool(sys.argv[2].split("=")[1] == 'true')

        # Confirm Slave Status
        print "Slave:", slave

        # Set-up Logging
        init_logging(log_level)

        HUB_LOGGER.info("Slave: %s", slave)

        # Register function to be called on shutdown
        signal.signal(signal.SIGINT, shutdown_scheduler)

        # Initialise Background Threads
        network_monitor_thread = threading.Thread(target=network_monitor)

        # Background thread will NOT finish with the main program
        # Start network_monitor() in a separate thread
        network_monitor_thread.start()
        HUB_LOGGER.info('Network monitor thread Started')

        priority_thread = threading.Thread(target=priority_work)

        # Background thread will NOT finish with the main program
        # Start priority_work() in a separate thread
        priority_thread.start()
        HUB_LOGGER.info('Priority work thread started')

        time_sensitive_thread = threading.Thread(target=time_sensitive_work)

        # Background thread will NOT finish with the main program
        # Start time_sensitive_work() in a separate thread
        time_sensitive_thread.start()
        HUB_LOGGER.info('Time sensitive work thread started')

        # initialise alert counts which must persist across calls
        alert_counts = {}

        # initialise impulse pins which must persist across calls
        impulse_pins = []

        HUB_LOGGER.info('Entering Main Loop...')
        while _loop_counter != 0:
            secs = datetime.datetime.now().second
            mins = datetime.datetime.now().minute
            hrs = datetime.datetime.now().hour
            mins_1_event = (secs == 0)
            mins_5_event = (secs == 0 and mins % 5 == 0)
            hrs_24_event = (secs == 0 and mins == 0 and hrs == 0)

            sleep_duration = 1.0  # to ensure we only process once

            if mins_1_event:

                HUB_LOGGER.info("mins_1_event - %s:%s", mins, secs)

                # Process Current Values
                current_values.process_current_values(alert_counts)

                # Process Current Thresholds/Init Impulses - not slaves
                if not slave:
                    monitor.check_complex_conditions()
                    impulses.initialise(impulse_pins)

            # Check for 5min boundary
            if mins_5_event:

                # Process Samples
                if not slave:
                    sampler.process_samples()
                    statistics.process_stats()

            if hrs_24_event:
                HUB_LOGGER.info("24 hour event - db/log management")
                monitor.truncate_samples()

                # Lookup email flag
                flag = helpers.get_user_setting("SummaryEnabled")
                HUB_LOGGER.info("Daily Summary Email Enabled: %s", flag)
                if flag is not None and flag.lower() == 'true':
                    statistics.daily_summary()

            time.sleep(sleep_duration)

        HUB_LOGGER.warn(
            "main_sched - Shutdown event Loop Counter: %s",
            _loop_counter)

    except Exception:
        # pop this error in main log - as it may be a logging error!
        print("Error in Main Loop type: {0} value: {1} line: {2} trace: {3}"
              .format(sys.exc_info()[0],
                      sys.exc_info()[1],
                      sys.exc_info()[2].tb_lineno,
                      traceback.format_exc()))


if __name__ == '__main__':
    main()
