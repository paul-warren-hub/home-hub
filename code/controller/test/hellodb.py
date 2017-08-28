#
# hellodb.py script to show PostgreSQL and Pyscopg together
#

import sys
import psycopg2
import psycopg2.extras

try:
    cstr = "dbname='hub' user='postgres' host='localhost' password='raspberry'"
    conn = psycopg2.connect(cstr)
    cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("SELECT * from \"Zone\"")
    rows = cur.fetchall()
    print "\nShow me the zones:\n"
    for row in rows:
        print row.get("ZoneID"), row.get("ZoneName")
except Exception:
    print("Unable to connect to the database")
    e = sys.exc_info()[0]
    print (e)
