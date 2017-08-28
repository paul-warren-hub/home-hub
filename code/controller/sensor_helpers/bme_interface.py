#
# Home Automation Hub
# Script to interface with Bme280 Sensor Type
#
# pylint: disable=broad-except
#
"""BM280 Sensor interface"""

import bme_serial
from ctypes import c_short

# Register Addresses
# Chip ID Register Address
REG_ID = 0xD0
REG_ID_CMD = 'A'
REG_DATA = 0xF7
REG_DATA_CMD = 'K'
REG_EPROM_1 = 'B'
REG_EPROM_2 = 'C'
REG_EPROM_3 = 'E'

REG_CONTROL_HUM = 0xF2
REG_HUM_MSB = 0xFD
REG_HUM_LSB = 0xFE


def get_short(data, index):

    """Get short"""

    # return two bytes from data as a signed 16-bit value
    return c_short((data[index+1] << 8) + data[index]).value


def get_ushort(data, index):

    """Get unsigned short"""

    # return two bytes from data as an unsigned 16-bit value
    return (data[index+1] << 8) + data[index]


def get_char(data, index):

    """Get character"""

    # return one byte from data as a signed char
    result = data[index]
    if result > 127:
        result -= 256
    return result


def get_uchar(data, index):

    """Get unsigned char"""

    # return one byte from data as an unsigned char
    result = data[index] & 0xFF
    return result


def read_bme280_id(tty_port, baud):

    """Read Bme280 id"""

    chip_meta = bme_serial.read_block_data(tty_port, baud, REG_ID_CMD)
    return chip_meta


def read_bme280_all(tty_port, baud):

    """Read Bme280 all"""

    # Read blocks of calibration data from EEPROM
    # See Page 22 data sheet
    cal1 = bme_serial.read_block_data(tty_port, baud, REG_EPROM_1)
    cal2 = bme_serial.read_block_data(tty_port, baud, REG_EPROM_2)
    cal3 = bme_serial.read_block_data(tty_port, baud, REG_EPROM_3)

    # Convert byte data to word values
    dig_t1 = get_ushort(cal1, 0)
    dig_t2 = get_short(cal1, 2)
    dig_t3 = get_short(cal1, 4)
    dig_p1 = get_ushort(cal1, 6)
    dig_p2 = get_short(cal1, 8)
    dig_p3 = get_short(cal1, 10)
    dig_p4 = get_short(cal1, 12)
    dig_p5 = get_short(cal1, 14)
    dig_p6 = get_short(cal1, 16)
    dig_p7 = get_short(cal1, 18)
    dig_p8 = get_short(cal1, 20)
    dig_p9 = get_short(cal1, 22)
    dig_h1 = get_uchar(cal2, 0)
    dig_h2 = get_short(cal3, 0)
    dig_h3 = get_uchar(cal3, 2)
    dig_h4 = get_char(cal3, 3)
    dig_h4 = (dig_h4 << 24) >> 20
    dig_h4 = dig_h4 | (get_char(cal3, 4) & 0x0F)
    dig_h5 = get_char(cal3, 5)
    dig_h5 = (dig_h5 << 24) >> 20
    dig_h5 = dig_h5 | (get_uchar(cal3, 4) >> 4 & 0x0F)
    dig_h6 = get_char(cal3, 6)

    # Read temperature/pressure/humidity
    data = bme_serial.read_block_data(tty_port, baud, REG_DATA_CMD)
    pres_raw = (data[0] << 12) | (data[1] << 4) | (data[2] >> 4)
    temp_raw = (data[3] << 12) | (data[4] << 4) | (data[5] >> 4)
    hum_raw = (data[6] << 8) | data[7]

    # Refine temperature
    var1 = (((temp_raw >> 3) - (dig_t1 << 1)) * dig_t2) >> 11
    var2 = (
        ((((temp_raw >> 4)-dig_t1)*((temp_raw >> 4)-dig_t1)) >> 12) * dig_t3
        ) >> 14
    t_fine = var1 + var2
    temperature = float(((t_fine * 5) + 128) >> 8)

    # Refine pressure and adjust for temperature
    var1 = t_fine / 2.0 - 64000.0
    var2 = var1 * var1 * dig_p6 / 32768.0
    var2 = var2 + var1 * dig_p5 * 2.0
    var2 = var2 / 4.0 + dig_p4 * 65536.0
    var1 = (dig_p3 * var1 * var1 / 524288.0 + dig_p2 * var1) / 524288.0
    var1 = (1.0 + var1 / 32768.0) * dig_p1
    if var1 == 0:
        pressure = 0
    else:
        pressure = 1048576.0 - pres_raw
        pressure = ((pressure - var2 / 4096.0) * 6250.0) / var1
        var1 = dig_p9 * pressure * pressure / 2147483648.0
        var2 = pressure * dig_p8 / 32768.0
        pressure = pressure + (var1 + var2 + dig_p7) / 16.0

    # Refine humidity
    humidity = t_fine - 76800.0
    humidity = (hum_raw - (dig_h4 * 64.0 + dig_h5 / 16384.0 * humidity)) * (dig_h2 / 65536.0 * (1.0 + dig_h6 / 67108864.0 * humidity * (1.0 + dig_h3 / 67108864.0 * humidity)))  # @IgnorePep8
    humidity = humidity * (1.0 - dig_h1 * humidity / 524288.0)
    if humidity > 100:
        humidity = 100
    elif humidity < 0:
        humidity = 0

    return temperature/100.0, pressure/100.0, humidity


def read_value(tty_port, baud, channel):

    """Read value"""

    temp_press_hum = read_bme280_all(tty_port, baud)
    return round(temp_press_hum[channel], 1)
