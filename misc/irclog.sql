CREATE TABLE message (
    message_pk serial NOT NULL,
    source_file character varying(14) NOT NULL,
    line integer NOT NULL,
    "timestamp" timestamp without time zone NOT NULL,
    nickname text NOT NULL,
    raw_text text NOT NULL,
    text text not null,
    deleted bool NOT NULL DEFAULT false,
    primary key(message_pk)
);

CREATE TABLE settings (
    key varchar(50) NOT NULL,
    value text NOT NULL,
    primary key (key)
);

