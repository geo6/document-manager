--

CREATE TABLE "users" (
  "id" serial NOT NULL,
  "login" character varying NOT NULL,
  "password" character varying NOT NULL,
  "email" character varying NOT NULL,
  "fullname" character varying NOT NULL
);

ALTER TABLE "users"
  ADD CONSTRAINT "users_id" PRIMARY KEY ("id");

--

CREATE TABLE "roles" (
  "id" serial NOT NULL,
  "name" character varying NOT NULL,
  "priority" integer NOT NULL
);

ALTER TABLE "roles"
  ADD CONSTRAINT "roles_id" PRIMARY KEY ("id");

--

CREATE TABLE "user_role" (
  "id_user" integer NOT NULL,
  "id_role" integer NOT NULL
);

ALTER TABLE "user_role"
  ADD CONSTRAINT "user_role_id_user_id_role" PRIMARY KEY ("id_user", "id_role");

ALTER TABLE "user_role"
  ADD FOREIGN KEY ("id_role") REFERENCES "roles" ("id"),
  ADD FOREIGN KEY ("id_user") REFERENCES "users" ("id");
