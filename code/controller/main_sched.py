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

# #import statistics

import helpers
import current_values

# #import sampler

# #import actuators
# #import timers

# #import monitor
# #import rules_engine

# #import impulses


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


# Routine that processes high priority tasks in background
def priority_work():

    """Priority work."""

    global _loop_counter
    while _loop_counter != 0:
        HUB_LOGGER.debug(
            "Doing Priority Work... _loop_counter: %s",
            _loop_counter)
        # #rules_engine.process_rules_from_event_queue()
        # #actuators.process_actuators()
        time.sleep(1)

    HUB_LOGGER.warn("PriorityWork closed")


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
        # #timers.process_timers()
        time.sleep(1)

    HUB_LOGGER.warn("TimeSensitiveWork closed")


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
        # Print sys.path
        print sys.path

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
        priority_thread = threading.Thread(target=priority_work)

        # Background thread will NOT finish with the main program
        # Start PriorityWork() in a separate thread
        priority_thread.start()
        HUB_LOGGER.info('Priority Work Thread Started')

        time_sensitive_thread = threading.Thread(target=time_sensitive_work)

        # Background thread will NOT finish with the main program
        # Start TimeSensitiveWork() in a separate thread
        time_sensitive_thread.start()
        HUB_LOGGER.info('TimeSensitive Work Thread Started')

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
                    # #monitor.check_complex_conditions()
                    # #impulses.initialise(impulse_pins)
                    pass

            # Check for 5min boundary
            if mins_5_event:

                # Process Samples
                if not slave:
                    # #sampler.process_samples()
                    # #statistics.process_stats()
                    pass

            if hrs_24_event:
                HUB_LOGGER.info("24 hour event - db/log management")
                # #monitor.truncate_samples()

                # Lookup email flag
                flag = helpers.get_user_setting("SummaryEnabled")
                HUB_LOGGER.info("Daily Summary Email Enabled: %s", flag)
                if flag is not None and flag.lower() == 'true':
                    # #statistics.daily_summary()
                    pass

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
