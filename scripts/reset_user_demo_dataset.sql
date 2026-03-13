-- Usage:
--   1. Edite la ligne INSERT INTO seed_config juste dessous avec TON email.
--   2. Lance: sqlite3 var/data.db ".read scripts/reset_user_demo_dataset.sql"
--   3. Connexion seedee: mot de passe Demo1234!

PRAGMA foreign_keys = OFF;
BEGIN TRANSACTION;

DROP TABLE IF EXISTS seed_config;
CREATE TEMP TABLE seed_config (
    email TEXT NOT NULL,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL
);

INSERT INTO seed_config (email, display_name, password_hash)
VALUES ('remplace-moi@example.com', 'Capitaine Carnet', '$2y$12$IagVXy0CibkrjT3kW7oQYemX08wlx9neLOgzLl5n6VXdQowpgw8vK');

INSERT OR IGNORE INTO user (id, email, roles, password, created_at, is_verified, google_id, oauth_provider, display_name)
SELECT 900001, email, '["ROLE_USER"]', password_hash, '2026-01-02 18:30:00', 1, NULL, NULL, display_name
FROM seed_config;

UPDATE user
SET roles = '["ROLE_USER"]',
    password = (SELECT password_hash FROM seed_config),
    created_at = COALESCE(created_at, '2026-01-02 18:30:00'),
    is_verified = 1,
    google_id = NULL,
    oauth_provider = NULL,
    display_name = (SELECT display_name FROM seed_config)
WHERE email = (SELECT email FROM seed_config);

DROP TABLE IF EXISTS seed_target_user;
CREATE TEMP TABLE seed_target_user AS
SELECT id
FROM user
WHERE email = (SELECT email FROM seed_config);

DELETE FROM entry_match
WHERE entry_id IN (
    SELECT e.id
    FROM entry e
    JOIN session s ON s.id = e.session_id
    JOIN game_group g ON g.id = s.group_id
    WHERE g.created_by_id = (SELECT id FROM seed_target_user)
);

DELETE FROM entry_score
WHERE entry_id IN (
    SELECT e.id
    FROM entry e
    JOIN session s ON s.id = e.session_id
    JOIN game_group g ON g.id = s.group_id
    WHERE g.created_by_id = (SELECT id FROM seed_target_user)
);

DELETE FROM entry
WHERE session_id IN (
    SELECT s.id
    FROM session s
    JOIN game_group g ON g.id = s.group_id
    WHERE g.created_by_id = (SELECT id FROM seed_target_user)
);

DELETE FROM session
WHERE group_id IN (
    SELECT id
    FROM game_group
    WHERE created_by_id = (SELECT id FROM seed_target_user)
);

DELETE FROM activity
WHERE group_id IN (
    SELECT id
    FROM game_group
    WHERE created_by_id = (SELECT id FROM seed_target_user)
);

DELETE FROM invite
WHERE group_id IN (
    SELECT id
    FROM game_group
    WHERE created_by_id = (SELECT id FROM seed_target_user)
)
OR created_by_id = (SELECT id FROM seed_target_user);

DELETE FROM group_member
WHERE group_id IN (
    SELECT id
    FROM game_group
    WHERE created_by_id = (SELECT id FROM seed_target_user)
);

DELETE FROM game_group
WHERE created_by_id = (SELECT id FROM seed_target_user);

DELETE FROM group_member WHERE id BETWEEN 980001 AND 980099;
DELETE FROM invite WHERE id BETWEEN 970001 AND 970099;
DELETE FROM entry_match WHERE id BETWEEN 960001 AND 960199;
DELETE FROM entry_score WHERE id BETWEEN 950001 AND 950399;
DELETE FROM entry WHERE id BETWEEN 940001 AND 940199;
DELETE FROM session WHERE id BETWEEN 930001 AND 930199;
DELETE FROM activity WHERE id BETWEEN 920001 AND 920099;
DELETE FROM game_group WHERE id BETWEEN 910101 AND 910199;
DELETE FROM user WHERE id BETWEEN 910001 AND 910006;

INSERT INTO user (id, email, roles, password, created_at, is_verified, google_id, oauth_provider, display_name) VALUES
    (910001, 'nova.demo@carnet.local', '["ROLE_USER"]', '$2y$12$IagVXy0CibkrjT3kW7oQYemX08wlx9neLOgzLl5n6VXdQowpgw8vK', '2026-01-02 18:40:00', 1, NULL, NULL, 'Nova'),
    (910002, 'lea.demo@carnet.local', '["ROLE_USER"]', '$2y$12$IagVXy0CibkrjT3kW7oQYemX08wlx9neLOgzLl5n6VXdQowpgw8vK', '2026-01-02 18:41:00', 1, NULL, NULL, 'Lea'),
    (910003, 'mehdi.demo@carnet.local', '["ROLE_USER"]', '$2y$12$IagVXy0CibkrjT3kW7oQYemX08wlx9neLOgzLl5n6VXdQowpgw8vK', '2026-01-02 18:42:00', 1, NULL, NULL, 'Mehdi'),
    (910004, 'yannis.demo@carnet.local', '["ROLE_USER"]', '$2y$12$IagVXy0CibkrjT3kW7oQYemX08wlx9neLOgzLl5n6VXdQowpgw8vK', '2026-01-02 18:43:00', 1, NULL, NULL, 'Yannis'),
    (910005, 'sarah.demo@carnet.local', '["ROLE_USER"]', '$2y$12$IagVXy0CibkrjT3kW7oQYemX08wlx9neLOgzLl5n6VXdQowpgw8vK', '2026-01-02 18:44:00', 1, NULL, NULL, 'Sarah'),
    (910006, 'enzo.demo@carnet.local', '["ROLE_USER"]', '$2y$12$IagVXy0CibkrjT3kW7oQYemX08wlx9neLOgzLl5n6VXdQowpgw8vK', '2026-01-02 18:45:00', 1, NULL, NULL, 'Enzo');

INSERT INTO game_group (id, name, created_at, created_by_id) VALUES
    (910101, 'Ranked Lab', '2026-01-03 19:00:00', (SELECT id FROM seed_target_user)),
    (910102, 'Salon du Dimanche', '2026-01-03 19:10:00', (SELECT id FROM seed_target_user));

INSERT INTO group_member (id, role, joined_at, group_id, user_id) VALUES
    (980001, 'OWNER', '2026-01-03 19:00:00', 910101, (SELECT id FROM seed_target_user)),
    (980002, 'MEMBER', '2026-01-03 19:01:00', 910101, 910001),
    (980003, 'MEMBER', '2026-01-03 19:02:00', 910101, 910002),
    (980004, 'MEMBER', '2026-01-03 19:03:00', 910101, 910003),
    (980005, 'MEMBER', '2026-01-03 19:04:00', 910101, 910004),
    (980006, 'OWNER', '2026-01-03 19:10:00', 910102, (SELECT id FROM seed_target_user)),
    (980007, 'MEMBER', '2026-01-03 19:11:00', 910102, 910003),
    (980008, 'MEMBER', '2026-01-03 19:12:00', 910102, 910004),
    (980009, 'MEMBER', '2026-01-03 19:13:00', 910102, 910005),
    (980010, 'MEMBER', '2026-01-03 19:14:00', 910102, 910006);

INSERT INTO activity (id, name, context_mode, created_at, group_id, created_by_id) VALUES
    (920001, 'Rocket League 1v1 Ladder', 'duel', '2026-01-04 20:00:00', 910101, (SELECT id FROM seed_target_user)),
    (920002, 'Rocket League 2v2 Scrims', 'duel_equipe', '2026-01-04 20:02:00', 910101, (SELECT id FROM seed_target_user)),
    (920003, 'Valorant Premier Night', 'groupe_vs_externe', '2026-01-04 20:04:00', 910101, (SELECT id FROM seed_target_user)),
    (920004, 'Chess Blitz', 'duel', '2026-01-04 20:06:00', 910101, (SELECT id FROM seed_target_user)),
    (920005, 'Skyjo Deluxe', 'ranking', '2026-01-04 20:08:00', 910102, (SELECT id FROM seed_target_user)),
    (920006, 'Belote du Dimanche', 'duel_equipe', '2026-01-04 20:10:00', 910102, (SELECT id FROM seed_target_user)),
    (920007, 'Fortnite Squad Night', 'groupe_vs_externe', '2026-01-04 20:12:00', 910101, (SELECT id FROM seed_target_user)),
    (920008, 'Counter-Strike 2 Scrim', 'groupe_vs_externe', '2026-01-04 20:14:00', 910101, (SELECT id FROM seed_target_user)),
    (920009, 'EA FC 26 Versus', 'duel', '2026-01-04 20:16:00', 910101, (SELECT id FROM seed_target_user)),
    (920010, 'Mario Kart Cup', 'ranking', '2026-01-04 20:18:00', 910102, (SELECT id FROM seed_target_user)),
    (920011, 'Catan League', 'ranking', '2026-01-04 20:20:00', 910102, (SELECT id FROM seed_target_user)),
    (920012, 'League of Legends Flex', 'groupe_vs_externe', '2026-01-04 20:22:00', 910101, (SELECT id FROM seed_target_user));

INSERT INTO session (id, title, played_at, created_at, unlisted_token, activity_id, group_id, created_by_id) VALUES
    (930001, 'RL Ladder Opening', '2026-01-11 21:00:00', '2026-01-11 20:20:00', NULL, 920001, 910101, (SELECT id FROM seed_target_user)),
    (930002, 'RL Ladder Mid Split', '2026-02-03 21:10:00', '2026-02-03 20:30:00', NULL, 920001, 910101, (SELECT id FROM seed_target_user)),
    (930003, 'RL Ladder Clutch Night', '2026-03-08 21:30:00', '2026-03-08 20:40:00', NULL, 920001, 910101, (SELECT id FROM seed_target_user)),
    (930004, 'RL 2v2 Warmup', '2026-02-14 22:00:00', '2026-02-14 21:15:00', NULL, 920002, 910101, (SELECT id FROM seed_target_user)),
    (930005, 'RL 2v2 Derby', '2026-03-15 22:10:00', '2026-03-15 21:25:00', NULL, 920002, 910101, (SELECT id FROM seed_target_user)),
    (930006, 'Valorant Placement', '2026-01-20 20:45:00', '2026-01-20 19:55:00', NULL, 920003, 910101, (SELECT id FROM seed_target_user)),
    (930007, 'Valorant Overtime', '2026-02-22 20:50:00', '2026-02-22 20:00:00', NULL, 920003, 910101, (SELECT id FROM seed_target_user)),
    (930008, 'Valorant Promotion Push', '2026-03-18 21:15:00', '2026-03-18 20:10:00', NULL, 920003, 910101, (SELECT id FROM seed_target_user)),
    (930009, 'Chess Bullet Lunch', '2026-01-05 12:30:00', '2026-01-05 12:00:00', NULL, 920004, 910101, (SELECT id FROM seed_target_user)),
    (930010, 'Chess Return Match', '2026-02-09 19:30:00', '2026-02-09 18:50:00', NULL, 920004, 910101, (SELECT id FROM seed_target_user)),
    (930011, 'Chess Tiebreak', '2026-03-12 19:45:00', '2026-03-12 19:00:00', NULL, 920004, 910101, (SELECT id FROM seed_target_user)),
    (930012, 'Skyjo Chill #1', '2026-01-13 20:00:00', '2026-01-13 19:20:00', NULL, 920005, 910102, (SELECT id FROM seed_target_user)),
    (930013, 'Skyjo Revanche #2', '2026-02-17 20:05:00', '2026-02-17 19:25:00', NULL, 920005, 910102, (SELECT id FROM seed_target_user)),
    (930014, 'Skyjo Masters #3', '2026-03-21 20:15:00', '2026-03-21 19:35:00', NULL, 920005, 910102, (SELECT id FROM seed_target_user)),
    (930015, 'Belote Givree', '2026-02-28 18:30:00', '2026-02-28 18:00:00', NULL, 920006, 910102, (SELECT id FROM seed_target_user)),
    (930016, 'Belote Finale', '2026-03-23 18:45:00', '2026-03-23 18:05:00', NULL, 920006, 910102, (SELECT id FROM seed_target_user));

-- V2: extension de saison (Avril/Mai) pour des courbes RL/Valorant plus complètes
INSERT INTO session (id, title, played_at, created_at, unlisted_token, activity_id, group_id, created_by_id) VALUES
    (930017, 'RL Ladder Spring #1', '2026-04-04 21:05:00', '2026-04-04 20:25:00', NULL, 920001, 910101, (SELECT id FROM seed_target_user)),
    (930018, 'RL Ladder Spring #2', '2026-04-19 21:20:00', '2026-04-19 20:40:00', NULL, 920001, 910101, (SELECT id FROM seed_target_user)),
    (930019, 'RL Ladder Spring #3', '2026-05-03 21:10:00', '2026-05-03 20:30:00', NULL, 920001, 910101, (SELECT id FROM seed_target_user)),
    (930020, 'RL Ladder Spring Finals', '2026-05-24 21:35:00', '2026-05-24 20:50:00', NULL, 920001, 910101, (SELECT id FROM seed_target_user)),
    (930021, 'Valorant Act II - Opener', '2026-04-06 20:55:00', '2026-04-06 20:05:00', NULL, 920003, 910101, (SELECT id FROM seed_target_user)),
    (930022, 'Valorant Act II - Mid', '2026-04-27 21:05:00', '2026-04-27 20:15:00', NULL, 920003, 910101, (SELECT id FROM seed_target_user)),
    (930023, 'Valorant Act II - High Elo', '2026-05-11 21:10:00', '2026-05-11 20:20:00', NULL, 920003, 910101, (SELECT id FROM seed_target_user)),
    (930024, 'Valorant Act II - Promotion', '2026-05-30 21:40:00', '2026-05-30 20:45:00', NULL, 920003, 910101, (SELECT id FROM seed_target_user));

INSERT INTO entry (id, type, label, created_at, session_id, group_id, created_by_id) VALUES
    (940001, 'match', 'BO3 ouverture', '2026-01-11 21:05:00', 930001, 910101, (SELECT id FROM seed_target_user)),
    (940002, 'match', 'BO5 split 2', '2026-02-03 21:15:00', 930002, 910101, (SELECT id FROM seed_target_user)),
    (940003, 'match', 'Finale ladder', '2026-03-08 21:35:00', 930003, 910101, (SELECT id FROM seed_target_user)),
    (940004, 'match', 'Scrim alpha', '2026-02-14 22:05:00', 930004, 910101, (SELECT id FROM seed_target_user)),
    (940005, 'match', 'Scrim revanche', '2026-03-15 22:15:00', 930005, 910101, (SELECT id FROM seed_target_user)),
    (940006, 'match', 'Placement game', '2026-01-20 20:50:00', 930006, 910101, (SELECT id FROM seed_target_user)),
    (940007, 'match', 'OT clutch', '2026-02-22 20:55:00', 930007, 910101, (SELECT id FROM seed_target_user)),
    (940008, 'match', 'Promo game', '2026-03-18 21:20:00', 930008, 910101, (SELECT id FROM seed_target_user)),
    (940009, 'match', 'Round 1', '2026-01-05 12:35:00', 930009, 910101, (SELECT id FROM seed_target_user)),
    (940010, 'match', 'Round 2', '2026-02-09 19:35:00', 930010, 910101, (SELECT id FROM seed_target_user)),
    (940011, 'match', 'Round 3', '2026-03-12 19:50:00', 930011, 910101, (SELECT id FROM seed_target_user)),
    (940012, 'score_simple', 'Table principale', '2026-01-13 20:10:00', 930012, 910102, (SELECT id FROM seed_target_user)),
    (940013, 'score_simple', 'Table principale', '2026-02-17 20:15:00', 930013, 910102, (SELECT id FROM seed_target_user)),
    (940014, 'score_simple', 'Table finale', '2026-03-21 20:20:00', 930014, 910102, (SELECT id FROM seed_target_user)),
    (940015, 'match', 'Manche 1', '2026-02-28 18:35:00', 930015, 910102, (SELECT id FROM seed_target_user)),
    (940016, 'match', 'Manche 2', '2026-03-23 18:50:00', 930016, 910102, (SELECT id FROM seed_target_user));

INSERT INTO entry (id, type, label, created_at, session_id, group_id, created_by_id) VALUES
    (940017, 'match', 'BO5 spring opener', '2026-04-04 21:10:00', 930017, 910101, (SELECT id FROM seed_target_user)),
    (940018, 'match', 'BO3 tactical switch', '2026-04-19 21:25:00', 930018, 910101, (SELECT id FROM seed_target_user)),
    (940019, 'match', 'BO5 tilt recover', '2026-05-03 21:15:00', 930019, 910101, (SELECT id FROM seed_target_user)),
    (940020, 'match', 'BO7 finals', '2026-05-24 21:40:00', 930020, 910101, (SELECT id FROM seed_target_user)),
    (940021, 'match', 'Act II opener', '2026-04-06 21:00:00', 930021, 910101, (SELECT id FROM seed_target_user)),
    (940022, 'match', 'Act II map control', '2026-04-27 21:10:00', 930022, 910101, (SELECT id FROM seed_target_user)),
    (940023, 'match', 'Act II clutch chain', '2026-05-11 21:15:00', 930023, 910101, (SELECT id FROM seed_target_user)),
    (940024, 'match', 'Act II promotion game', '2026-05-30 21:45:00', 930024, 910101, (SELECT id FROM seed_target_user));

INSERT INTO entry_match (id, entry_id, home_name, away_name, home_score, away_score, home_user_id, away_user_id) VALUES
    (960001, 940001, (SELECT display_name FROM seed_config), 'Nova', 3, 1, (SELECT id FROM seed_target_user), 910001),
    (960002, 940002, 'Nova', (SELECT display_name FROM seed_config), 4, 5, 910001, (SELECT id FROM seed_target_user)),
    (960003, 940003, (SELECT display_name FROM seed_config), 'Lea', 2, 3, (SELECT id FROM seed_target_user), 910002),
    (960004, 940004, 'Fox & Nova', 'Lea & Mehdi', 4, 2, (SELECT id FROM seed_target_user), 910002),
    (960005, 940005, 'Fox & Mehdi', 'Nova & Yannis', 1, 4, (SELECT id FROM seed_target_user), 910001),
    (960006, 940006, 'Team Carnet', 'Raiders EU', 13, 11, (SELECT id FROM seed_target_user), NULL),
    (960007, 940007, 'Team Carnet', 'Hydra Five', 14, 16, (SELECT id FROM seed_target_user), NULL),
    (960008, 940008, 'Team Carnet', 'Orbit Academy', 13, 7, (SELECT id FROM seed_target_user), NULL),
    (960009, 940009, (SELECT display_name FROM seed_config), 'Mehdi', 1, 0, (SELECT id FROM seed_target_user), 910003),
    (960010, 940010, 'Mehdi', (SELECT display_name FROM seed_config), 1, 1, 910003, (SELECT id FROM seed_target_user)),
    (960011, 940011, (SELECT display_name FROM seed_config), 'Sarah', 0, 1, (SELECT id FROM seed_target_user), 910005),
    (960012, 940015, 'Fox & Sarah', 'Mehdi & Yannis', 162, 148, (SELECT id FROM seed_target_user), 910003),
    (960013, 940016, 'Fox & Enzo', 'Sarah & Yannis', 151, 164, (SELECT id FROM seed_target_user), 910005);

INSERT INTO entry_match (id, entry_id, home_name, away_name, home_score, away_score, home_user_id, away_user_id) VALUES
    (960014, 940017, (SELECT display_name FROM seed_config), 'Nova', 1, 3, (SELECT id FROM seed_target_user), 910001),
    (960015, 940018, (SELECT display_name FROM seed_config), 'Lea', 4, 2, (SELECT id FROM seed_target_user), 910002),
    (960016, 940019, 'Mehdi', (SELECT display_name FROM seed_config), 1, 4, 910003, (SELECT id FROM seed_target_user)),
    (960017, 940020, (SELECT display_name FROM seed_config), 'Yannis', 5, 3, (SELECT id FROM seed_target_user), 910004),
    (960018, 940021, 'Team Carnet', 'Helios Core', 11, 13, (SELECT id FROM seed_target_user), NULL),
    (960019, 940022, 'Team Carnet', 'Void Hunters', 13, 10, (SELECT id FROM seed_target_user), NULL),
    (960020, 940023, 'Team Carnet', 'Monarch Five', 14, 12, (SELECT id FROM seed_target_user), NULL),
    (960021, 940024, 'Team Carnet', 'Apex Academy', 13, 8, (SELECT id FROM seed_target_user), NULL);

INSERT INTO entry_score (id, participant_name, score, entry_id, user_id) VALUES
    (950001, (SELECT display_name FROM seed_config), 88, 940012, (SELECT id FROM seed_target_user)),
    (950002, 'Mehdi', 76, 940012, 910003),
    (950003, 'Yannis', 64, 940012, 910004),
    (950004, 'Sarah', 71, 940012, 910005),
    (950005, (SELECT display_name FROM seed_config), 54, 940013, (SELECT id FROM seed_target_user)),
    (950006, 'Mehdi', 62, 940013, 910003),
    (950007, 'Yannis', 58, 940013, 910004),
    (950008, 'Enzo', 79, 940013, 910006),
    (950009, (SELECT display_name FROM seed_config), 47, 940014, (SELECT id FROM seed_target_user)),
    (950010, 'Sarah', 51, 940014, 910005),
    (950011, 'Yannis', 69, 940014, 910004),
    (950012, 'Enzo', 73, 940014, 910006);

INSERT INTO invite (id, email, token, role, created_at, expires_at, accepted_at, group_id, created_by_id) VALUES
    (970001, 'ami-a-inviter@example.com', 'demo-invite-ranked-lab', 'MEMBER', '2026-03-24 10:00:00', '2026-03-31 10:00:00', NULL, 910101, (SELECT id FROM seed_target_user)),
    (970002, 'cousine-bis@example.com', 'demo-invite-salon', 'MEMBER', '2026-03-24 10:05:00', '2026-03-31 10:05:00', NULL, 910102, (SELECT id FROM seed_target_user));

DROP TABLE IF EXISTS seed_target_user;
DROP TABLE IF EXISTS seed_config;

COMMIT;
PRAGMA foreign_keys = ON;