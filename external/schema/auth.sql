CREATE SCHEMA auth;

CREATE TABLE auth.users (
	id serial PRIMARY KEY,
	username varchar(64) NOT NULL UNIQUE,
	login_password varchar(512) NOT NULL,
	status varchar(16) NOT NULL DEFAULT 'Active' CHECK(status IN('Active', 'Inactive', 'Disabled')),
	date_created timestamp DEFAULT CURRENT_TIMESTAMP,
	date_last_accessed timestamp DEFAULT NULL,
	last_accessed_from varchar(16) NOT NULL
);

CREATE TABLE auth.user_email_addresses (
	user_id integer NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	email_address varchar(128) NOT NULL PRIMARY KEY
);

CREATE TABLE auth.user_public_keys (
	id serial PRIMARY KEY,
	user_id integer NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	public_key varchar(4096) NOT NULL
);

CREATE TABLE auth.user_sessions (
	id varchar(32) PRIMARY KEY,
	user_id integer NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	last_activity timestamp NOT NULL,
	remote_address varchar(16) NOT NULL,
	rebuild_acl boolean DEFAULT TRUE
);

CREATE TABLE auth.roles (
	id serial PRIMARY KEY,
	name varchar(32) UNIQUE NOT NULL
);

CREATE TABLE auth.actions (
	id serial PRIMARY KEY,
	name varchar(32) UNIQUE NOT NULL
);

CREATE TABLE auth.user_roles (
	user_id integer REFERENCES auth.users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	role_id integer REFERENCES auth.roles(id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (user_id, role_id)
);

CREATE TABLE auth.user_permissions (
	id serial PRIMARY KEY,
	user_id integer REFERENCES auth.users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	type varchar(32) DEFAULT NULL,
	key varchar(64) DEFAULT NULL,
	field varchar(32) DEFAULT NULL,
	bit_value integer NOT NULL,
	UNIQUE (user_id, type, key, field)
);

CREATE TABLE auth.role_permissions (
	id serial PRIMARY KEY,
	role_id integer REFERENCES auth.roles(id) ON DELETE CASCADE ON UPDATE CASCADE,
	type varchar(32) DEFAULT NULL,
	key varchar(64) DEFAULT NULL,
	field varchar(32) DEFAULT NULL,
	bit_value integer NOT NULL,
	UNIQUE (role_id, type, key, field)
);

CREATE TABLE auth.login_attempts (
	user_id integer REFERENCES auth.users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	remote_address varchar(45) NOT NULL, /* Supports IPv6 and possible IPv4 tunneling representation */
	date_occurred timestamp DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (user_id, remote_address, date_occurred),
	PRIMARY KEY (remote_address, date_occurred)
);

/* DEFAULT DATA */

INSERT INTO auth.roles (name) VALUES('Administrator');

INSERT INTO auth.actions (name) VALUES ('create');
INSERT INTO auth.actions (name) VALUES ('remove');
INSERT INTO auth.actions (name) VALUES ('update');
INSERT INTO auth.actions (name) VALUES ('manage');
INSERT INTO auth.actions (name) VALUES ('select');

INSERT INTO auth.role_permissions (role_id, type, key, field, bit_value) VALUES(
	(SELECT id FROM auth.roles WHERE name = 'Administrator'),
	NULL,
	NULL,
	NULL,
	POW(2, (SELECT id FROM auth.actions WHERE name = 'create')) +
	POW(2, (SELECT id FROM auth.actions WHERE name = 'remove')) +
	POW(2, (SELECT id FROM auth.actions WHERE name = 'update')) +
	POW(2, (SELECT id FROM auth.actions WHERE name = 'manage')) +
	POW(2, (SELECT id FROM auth.actions WHERE name = 'select'))
);
