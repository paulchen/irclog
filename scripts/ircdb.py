#!/usr/bin/python3

import os, psycopg2, psycopg2.extras, time, sys, re, fcntl, logging, configparser, random, datetime


last_update = 0
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


def get_random_color():
    available_colors = [ '22ff22', '0022ff', 'ff0000', '00aaaa', 'ff00ff', 'ffa500', 'cc0000',
                         'cc0000', '0000cc', '0080c0', '8080c0', 'ff0080', '800080', '688e23',
                         '408800', '808000', '000000', '00ff00', '0080ff', 'ff8000', '800000',
                         'fb31fb' ]

    return random.choice(available_colors)


def get_user(username):
    cur = conn.cursor()
    cur.execute("""SELECT user_pk FROM "user" WHERE username = %s""", (username.strip(), ))
    row = cur.fetchone()
    if row is None:
        cur.execute("""INSERT INTO "user" (username, color) VALUES (%s, %s) RETURNING user_pk""", (username.strip(), get_random_color()))
        user_id = cur.fetchone()[0]
    else:
        user_id = row[0]
    cur.close()
    return user_id


def extract_nickname(line):
    message_pattern = r'^[^ ]+ <(.)([^>]+)>'
    match = re.match(message_pattern, line)
    if match is not None:
        user_flag = match.group(1)
        if user_flag == ' ':
            user_flag = ''

        user_id = get_user(match.group(2))

        return (user_flag, user_id, 0)

    modechange_pattern = r'^.*-!- mode/.*by ([^ ]+)'
    match = re.match(modechange_pattern, line)
    if match is not None:
        user_id = get_user(match.group(1))

        return ('', user_id, 1)

    other_servicemsg_pattern = r'^.*-!- ([^ ]+) \['
    match = re.match(other_servicemsg_pattern, line)
    if match is not None:
        user_id = get_user(match.group(1))

        return ('', user_id, 2)

    action_pattern = r'^[^ ]+  \* ([^ ]+) '
    match = re.match(action_pattern, line)
    if match is not None:
        user_id = get_user(match.group(1))

        return ('', user_id, 3)

    nickchange_pattern = r'^[^ ]+ -!- ([^ ]+) is now known as'
    match = re.match(nickchange_pattern, line)
    if match is not None:
        user_id = get_user(match.group(1))

        return ('', user_id, 4)

    topicchange_pattern = r'^[^ ]+ -!- ([^ ]+) changed the topic of'
    match = re.match(topicchange_pattern, line)
    if match is not None:
        user_id = get_user(match.group(1))

        return ('', user_id, 5)

    kicked_pattern = r'^[^ ]+ -!- ([^ ]+) was kicked from'
    match = re.match(kicked_pattern, line)
    if match is not None:
        user_id = get_user(match.group(1))

        return ('', user_id, 6)

    irssi_pattern = r'^[^ ]+ -!- Irssi:'
    match = re.match(irssi_pattern, line)
    if match is not None:
        return ('', None, 7)

    return ('', None, -1)


def extract_text(line):
    text_pattern = r'^[^ ]+ <[^>]+> (.*)'
    match = re.match(text_pattern, line)
    if match is None:
        text_pattern = r'^[^ ]+ (.*)'
        match = re.match(text_pattern, line)
        if match is None:
            return line
    return match.group(1)


def process_file(filename, short_name, channel):
    logger.debug('Processing file %s, channel #%s' % (short_name, channel['name']))

    match = re.match(filename_pattern, short_name)
    date_string = match.group(1)

    cur = conn.cursor()
    cur.execute("SELECT COALESCE(MAX(line), 0) FROM message where source_file = %s AND channel_fk = %s", (short_name, channel['channel_pk']))
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
                (user_flag, user_id, message_type) = extract_nickname(line)
                text = extract_text(line)

                if timestamp is not None:
                    logger.debug('Inserting line %s' % line_number)

                    cur.execute("""INSERT INTO message (source_file, line, timestamp, user_fk, raw_text, text, user_flag, type, channel_fk) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)""", (short_name, line_number, timestamp, user_id, line, text, user_flag, message_type, channel['channel_pk']))
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


cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
cur.execute("""SELECT channel_pk, name, visible_shouts, last_update, directory FROM channel WHERE active = TRUE""")
channels = cur.fetchall()
cur.close()

for channel in channels:
    last_update = channel['last_update']
    directory = channel['directory']

    files = []
    for f in os.listdir(directory):
        match = re.match(filename_pattern, f)
        if match is not None:
            filename = os.path.join(directory, f)
            st = os.stat(filename)
            mtime = datetime.datetime.fromtimestamp(st.st_mtime)
            if mtime > channel['last_update']:
                files.append(f)

    files.sort()

    messages_added = 0
    for f in files:
        filename = os.path.join(directory, f)
        messages_added += process_file(filename, f, channel)

    cur = conn.cursor()
    total_messages = channel['visible_shouts']
    if messages_added > 0:
        cur.execute("""SELECT COUNT(*) FROM message WHERE deleted = false AND channel_fk = %s""", (channel['channel_pk'], ))
        row = cur.fetchone()
        total_messages = row[0]

    cur.execute("""UPDATE channel SET visible_shouts = %s, last_update = NOW() WHERE channel_pk = %s""", (total_messages, channel['channel_pk']))

    conn.commit()
    cur.close()

conn.close()

logger.debug('Script successfully completed')

