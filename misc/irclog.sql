CREATE TABLE "user" (
    user_pk serial NOT NULL,
    username text NOT NULL,
    color varchar(6) NOT NULL,
    PRIMARY KEY (user_pk)
);

CREATE TABLE message (
    message_pk serial NOT NULL,
    source_file character varying(14) NOT NULL,
    line integer NOT NULL,
    "timestamp" timestamp without time zone NOT NULL,
    user_fk integer,
    raw_text text NOT NULL,
    text text not null,
    deleted bool NOT NULL DEFAULT false,
    user_flag varchar(1) not null default '',
    html text,
    "type" integer not null default -1,
    primary key (message_pk),
    foreign key (user_fk) references "user" (user_pk)
);

CREATE TABLE settings (
    key varchar(50) NOT NULL,
    value text NOT NULL,
    primary key (key)
);

CREATE TABLE accounts (
    id serial NOT NULL,
    username text NOT NULL,
    hash text NOT NULL,
    primary key (id)
);

