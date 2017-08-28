#
# Home Automation Hub
# Helper Script to interface with SPI
#

"""Bme serial interface"""

import time
import logging
import serial

HUB_LOGGER = logging.getLogger('HubLogger')


def read_block_data(tty_port, baud, cmd):

    """Read block data"""

    # Initialise serial port
    ser = serial.Serial(tty_port)
    ser.baudrate = baud
    ser.timeout = 10  # 10 second timeout
    ser.write_timeout = 10  # 10 second timeout

    # Transmit the supplied command
    HUB_LOGGER.debug("Reading block data for command: %s", cmd)

    rx_result_str = ''
    ser.write(cmd)
    time.sleep(2)
    wait_timer = 10
    while ser.inWaiting() == 0 and wait_timer > 0:
        time.sleep(1)
        wait_timer -= 1
        HUB_LOGGER.debug("waiting for bytes to read...")
    while ser.inWaiting() > 0:
        read = bytes(ser.read())
        rx_result_str += read

    results = [int(x) for x in rx_result_str.split(' ') if x.strip().isdigit()]
    HUB_LOGGER.debug("Block Data: %s", results)

    return results
