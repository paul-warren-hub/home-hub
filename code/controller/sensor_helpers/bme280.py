#
# Home Automation Hub
# Helper Script to process different Sensor Types
#
# pylint: disable=broad-except
#
"""BM280 Sensor Helper"""

import sys
import logging
import bme_interface


# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def bme280(cur_val, method_params):

    """Read BM280 sensor"""

    try:
        tty = method_params[0]
        baud = method_params[1]
        channel = int(method_params[2])
        HUB_LOGGER.info(
            "current value: %s tty: %s baudrate: %s channel: %s",
            cur_val,
            tty,
            baud,
            channel)

        # Read the BME280 Temperature, Pressure, Humidity via serial i/face tty

        result = -9999.0

        # More efficient to read all 3 parameters and return required one
        temp_press_hum = bme_interface.read_bme280_all(tty, baud)
        HUB_LOGGER.info(
            "temp: %s pressure: %s humidity: %s",
            temp_press_hum[0],
            temp_press_hum[1],
            temp_press_hum[2])
        result = round(temp_press_hum[channel], 1)

    except Exception:
        HUB_LOGGER.error("Unable to process bme sensor")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)

    # return result and optional timestamp
    return (result, )
