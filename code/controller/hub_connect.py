#
# hub_connect.py script to connect to PostgreSQL
#

"""Return a database connection and cursor."""

import logging
import psycopg2.extras

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def get_connection():
    """Return a database connection."""
    try:
        cstr = ("dbname='hub' user='postgres' "
                "host='localhost' password='raspberry'")
        conn = psycopg2.connect(cstr)
    except psycopg2.Error as ex:
        HUB_LOGGER.error(
            "Unable to connect to the database. "
            "Error in hub_connect.get_connection: %s",
            ex.pgerror)
    return conn


def get_cursor(conn):
    """Return a database cursor."""
    try:
        if conn is None:
            conn = get_connection()
        cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    except psycopg2.Error as ex:
        HUB_LOGGER.error(
            "Error in hub_connect.get_cursor: %s",
            ex.pgerror)
    return cur
