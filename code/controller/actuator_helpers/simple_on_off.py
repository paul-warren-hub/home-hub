#
# Home Automation Hub
# Helper Script to process different Actuator Types
#
# Catching too general exception
# pylint: disable=W0703
#
"""Actuator helpers"""

import sys
import logging
import RPi.GPIO as GPIO


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')

# BCM Pin References
GPIO.setmode(GPIO.BCM)
GPIO.setwarnings(False)


def simple_on_off(cur_val, method_params):

    """Simple On/Off"""

    try:

        bcm_pin = int(method_params[0])
        HUB_LOGGER.debug("In simple_on_off %s %s ", cur_val, bcm_pin)
        status = -1
        if bcm_pin is not None:
            func = GPIO.gpio_function(bcm_pin)
            is_output = (func == GPIO.OUT)
            HUB_LOGGER.debug("Pin Mode %s %s %s", func, GPIO.OUT, is_output)

            HUB_LOGGER.debug("Making Output always")
            GPIO.setup(bcm_pin, GPIO.OUT)

            result = (cur_val > 0)
            cur_state = GPIO.input(bcm_pin)
            requires_changing = (GPIO.input(bcm_pin) != cur_val)
            HUB_LOGGER.debug(
                "Reading Output %d = %s requires_changing? %s",
                bcm_pin,
                cur_state,
                requires_changing)

            # turn on/off?
            if requires_changing:
                HUB_LOGGER.debug(
                    "Setting Output %d to %s ...",
                    bcm_pin,
                    result)
                GPIO.output(bcm_pin, result)
            status = result

        return status

    except Exception:
        HUB_LOGGER.error("Error in simple_on_off")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)
