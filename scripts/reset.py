#!/usr/bin/python3

import os, psycopg2, sys, configparser


timestamp_file = os.path.dirname(os.path.realpath(__file__)) + '/../tmp/update'
config_file = os.path.dirname(os.path.realpath(__file__)) + '/../config.ini'

settings = configparser.ConfigParser()
settings.read(config_file)

connect_string = "dbname='%s' user='%s' host='%s' password='%s' port='%s'" % (settings['general']['db_name'], settings['general']['db_user'], settings['general']['db_host'], settings['general']['db_pass'], settings['general']['db_port'])
try:
    conn = psycopg2.connect(connect_string)
except:
    logger.error('Database error')
    sys.exit(1)


cur = conn.cursor()
cur.execute("""TRUNCATE TABLE message""")

cur.execute("""ALTER SEQUENCE message_message_pk_seq RESTART""")

conn.commit()
cur.close()

conn.close()

os.unlink(timestamp_file)

