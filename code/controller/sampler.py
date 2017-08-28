#
# Home Automation Hub
# Script to sample current values
# pylint: disable=broad-except
#

"""Sampler"""

import sys
import logging
import hub_connect
import sampler_helpers

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def print_sample_defs(sample_defs):

    """Print Sample Defs"""

    HUB_LOGGER.info("Sample Defs")
    for sample_def in sample_defs:
        HUB_LOGGER.info(
            "\t%d %d %s %d %s",
            sample_def.get('SampleDefID'),
            sample_def.get('SampleTypeID'),
            sample_def.get('SampleTypeName'),
            sample_def.get('SensorEntryID'),
            sample_def.get('CurrentValue'))


def process_samples():

    """Process Samples"""

    try:
        # Establish database Connection
        conn = hub_connect.get_connection()
        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)
        select_sql = 'SELECT * from "vwSampleDefs" ORDER BY "SampleDefID"'
        cur.execute(select_sql)
        # Read results into python list
        sample_defs = cur.fetchall()
        print_sample_defs(sample_defs)
        HUB_LOGGER.info("Processing Sample Definitions")
        for sample_def in sample_defs:
            sensor_id = int(sample_def.get('SensorEntryID'))
            cur_val = float(sample_def.get('CurrentValue'))
            method_to_call = sample_def.get('SampleTypeName').lower()
            HUB_LOGGER.debug("method_to_call: %s", method_to_call)
            max_val = sample_def.get('MaxValue')
            min_val = sample_def.get('MinValue')
            HUB_LOGGER.debug(
                "max: %s[%s] min: %s[%s]",
                max_val,
                type(max_val),
                min_val,
                type(min_val)
                )
            method = getattr(sampler_helpers, method_to_call)
            result = method(sensor_id, cur_val)
            HUB_LOGGER.debug(
                "%s => %s[%s]",
                cur_val,
                result,
                type(result)
                )

            # Insert Database with sample value?
            if ((result is not None) and
                (max_val is None or result <= max_val) and
                    (min_val is None or result >= min_val)):
                cur.execute(
                    'INSERT INTO "Sample" ("SensorEntryID","Value")'
                    ' VALUES (%s, %s);',
                    (sensor_id, result))
            else:
                HUB_LOGGER.warning(
                    "Excluding invalid sample from database %s",
                    result)
        conn.commit()

        # Close communication with the database
        cur.close()
        conn.close()

    except Exception:
        HUB_LOGGER.error("Unable to process samples")
        HUB_LOGGER.error("%s %s %s",
                         sys.exc_info()[0],
                         sys.exc_info()[1],
                         sys.exc_info()[2].tb_lineno)
