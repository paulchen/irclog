#!/usr/bin/python3

import os, psycopg2, configparser, math, threading, subprocess


last_update = 0
directory = '/home/ircbot/.irssi/logs/localhost/#chatbox'
timestamp_file = os.path.dirname(os.path.realpath(__file__)) + '/../tmp/update'
logfile = os.path.dirname(os.path.realpath(__file__)) + '/../log/update.log'
config_file = os.path.dirname(os.path.realpath(__file__)) + '/../config.ini'
php_script = os.path.dirname(os.path.realpath(__file__)) + '/update_html.php'

settings = configparser.ConfigParser()
settings.read(config_file)


connect_string = "dbname='%s' user='%s' host='%s' password='%s' port='%s'" % (settings['general']['db_name'], settings['general']['db_user'], settings['general']['db_host'], settings['general']['db_pass'], settings['general']['db_port'])
try:
    conn = psycopg2.connect(connect_string)
except:
    logger.error('Database error')
    sys.exit(1)



cur = conn.cursor()
cur.execute("""UPDATE message SET html = NULL WHERE html IS NOT NULL""")
conn.commit()

cur.execute("""SELECT COUNT(*) FROM message WHERE deleted = false""")
row = cur.fetchone()
total_messages = row[0]

cur.close()
conn.close()


def invoke_script(php_script, page):
    subprocess.call([php_script, str(page)])


threads = []
for page in range(1, int(math.ceil(float(total_messages)/10000))+1):
    t = threading.Thread(target = invoke_script, args = (php_script, page))
    t.start()
    threads.append(t)

for t in threads:
    t.join()


