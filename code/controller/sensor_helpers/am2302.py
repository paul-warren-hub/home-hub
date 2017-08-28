#
# Home Automation Hub
# Helper Script to process different Sensor Types
#
# Catching too general exception
# pylint: disable=W0703
#

"""AM2302 Sensor Helper"""

import sys
import logging
import Adafruit_DHT

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def am2302(cur_val, method_params):

    """Evaluate expression."""

    try:
        sensor = Adafruit_DHT.AM2302
        pin = method_params[0]
        channel = method_params[1]
        sample_count = int(method_params[2])
        HUB_LOGGER.info(
            "pin: %s channel: %s count: %s", pin, channel, sample_count)
        # Note that sometimes you won't get a reading and
        # the results will be null (because Linux can't
        # guarantee the timing of calls to read the sensor).

        # In addition, long cable runs can exacerbate errors
        # Take multiple readings and only accept if all agree
        # Note that this resolves transmission errors - not measurement errors
        result = -9999.0
        valid = False
        retry_count = 3
        retry_index = retry_count
        while valid is False and retry_index > 0:
            sample_index = sample_count
            samples = []
            humidity, temperature = Adafruit_DHT.read_retry(sensor, pin)
            while sample_index > 0:
                if channel == '0':
                    samples.append(temperature)
                else:
                    samples.append(humidity)
                sample_index -= 1
            HUB_LOGGER.info("Sample array %s:", samples)
            # Now check they are all the same
            # This slices all but first, and compares with all but last!
            valid = (samples[1:] == samples[:-1])
            HUB_LOGGER.info("All Samples Match? %s:", valid)
            if valid:
                # take the first sample as result
                result = samples[0]
                break
            retry_index -= 1
            HUB_LOGGER.info(
                "Retry Num: %s",
                str(retry_count - retry_index + 1))

    except Exception:
        HUB_LOGGER.error("Unable to process sensor %s %s %s",
                         sys.exc_info()[0],
                         sys.exc_info()[1],
                         sys.exc_info()[2].tb_lineno)

    # return result and optional timestamp
    return (result, )
