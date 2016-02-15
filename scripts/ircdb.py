#!/usr/bin/python3

import os, psycopg2, time, sys, re, fcntl, logging, configparser


last_update = 0
directory = '/home/ircbot/.irssi/logs/localhost/#chatbox'
timestamp_file = '/home/ircbot/ircdb/update'
timestamp_file = os.path.dirname(os.path.realpath(__file__)) + '/../tmp/update'
logfile = os.path.dirname(os.path.realpath(__file__)) + '/../log/update.log'
config_file = os.path.dirname(os.path.realpath(__file__)) + '/../config.ini'

settings = configparser.ConfigParser()
settings.read(config_file)


logger = logging.getLogger()
handler = logging.FileHandler(logfile)
handler.setFormatter(logging.Formatter('%(asctime)s %(name)-12s %(levelname)-8s %(message)s'))
logger.addHandler(handler)
logger.setLevel(logging.DEBUG)


logger.debug('Script invoked')

start_time = time.time()

filename_pattern = r'^([0-9]{4}\-[0-9]{2}\-[0-9]{2})\.log$'

fh = 0

def run_once():
    global fh
    fh = open(os.path.realpath(__file__), 'r')
    try:
        fcntl.flock(fh, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except:
        logger.debug('Already running, terminating now')
        os._exit(0)


def extract_timestamp(line, date_string):
    timestamp_pattern = r'^([0-9]{2}:[0-9]{2}:[0-9]{2}) '
    match = re.match(timestamp_pattern, line)
    if match is None:
        return None
    time_string = match.group(1)
    return date_string + " " + time_string


def extract_nickname(line):
    nickname_pattern = r'^[^ ]+ <.([^>]+)>'
    match = re.match(nickname_pattern, line)
    if match is None:
        return ''
    return match.group(1)


def extract_text(line):
    text_pattern = r'^[^ ]+ <[^>]+> (.*)'
    match = re.match(text_pattern, line)
    if match is None:
        text_pattern = r'^[^ ]+ (.*)'
        match = re.match(text_pattern, line)
        if match is None:
            return line
    return match.group(1)


def process_file(filename, short_name):
    logger.debug('Processing file %s' % short_name)

    match = re.match(filename_pattern, short_name)
    date_string = match.group(1)

    cur = conn.cursor()
    cur.execute("SELECT COALESCE(MAX(line), 0) FROM message where source_file = %s", (short_name, ))
    row = cur.fetchone()
    max_line = row[0]
    cur.close()

    logger.debug('Lines already known: %s' % max_line)

    messages_added = 0
    with open(filename, 'r') as f:
        line_number = 0
        cur = conn.cursor()
        for line in f:
            if line_number > max_line:
                timestamp = extract_timestamp(line, date_string)
                nickname = extract_nickname(line)
                text = extract_text(line)

                if timestamp is not None:
                    logger.debug('Inserting line %s' % line_number)

                    cur.execute("""INSERT INTO message (source_file, line, timestamp, nickname, raw_text, text) VALUES (%s, %s, %s, %s, %s, %s)""", (short_name, line_number, timestamp, nickname, line, text))
                    messages_added += 1

            line_number += 1

        f.close()
        conn.commit()
        cur.close()

    return messages_added


run_once()

time.sleep(2)

connect_string = "dbname='%s' user='%s' host='%s' password='%s' port='%s'" % (settings['general']['db_name'], settings['general']['db_user'], settings['general']['db_host'], settings['general']['db_pass'], settings['general']['db_port'])
try:
    conn = psycopg2.connect(connect_string)
except:
    logger.error('Database error')
    sys.exit(1)


if not os.path.exists(timestamp_file):
    last_update = 0
else:
    st = os.stat(timestamp_file)
    last_update = st.st_mtime

files = []
for f in os.listdir(directory):
    match = re.match(filename_pattern, f)
    if match is not None:
        filename = os.path.join(directory, f)
        st = os.stat(filename)
        mtime = st.st_mtime
        if mtime > last_update:
            files.append(f)

files.sort()

messages_added = 0
for f in files:
    filename = os.path.join(directory, f)
    messages_added += process_file(filename, f)

if messages_added > 0:
    cur = conn.cursor()
    cur.execute("""SELECT COUNT(*) FROM message WHERE deleted = false""")
    row = cur.fetchone()
    total_messages = row[0]

    cur.execute("""INSERT INTO SETTINGS (key, value) VALUES (%s, %s) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value""", ('visible_shouts', total_messages))

    conn.commit()
    cur.close()

conn.close()

with open(timestamp_file, 'a'):
    os.utime(timestamp_file, (start_time, start_time))

logger.debug('Script successfully completed')

