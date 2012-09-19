-- We're abstracting our storage to a pair store with all logic in the app.
-- Therefore, DENORMALIZE ALL THE THINGS.
create table if not exists matches (
       matchid int,
       player bigint,
       letters varchar(26),
       timelimit int,
       words varchar(16535),
       details varchar(2047),
       starttime int, -- unixtime
       primary key (matchid, player),
       index idx_player (player)
)
engine = InnoDB;

-- For everything else
create table if not exists metadata (
       objectid bigint,
       metafield varchar(31),
       metavalue varchar(16535),
       primary key (objectid, metafield),
       index idx_field (metafield)
)
engine = InnoDB;