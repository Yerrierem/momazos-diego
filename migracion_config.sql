-- Ejecuta este script UNA sola vez (despues de migracion_juego.sql)
USE MOMAZOS_DIEGO;

-- Configuracion de cada partida (la cambia el anfitrion en el lobby)
ALTER TABLE partidas
    ADD COLUMN total_rondas TINYINT NOT NULL DEFAULT 3,      -- 1, 3, 5, 7 o 9
    ADD COLUMN seg_crear SMALLINT NOT NULL DEFAULT 150,      -- 60, 90, 120, 150, 180, 210, 240 o 270
    ADD COLUMN max_rerolls TINYINT NOT NULL DEFAULT 10,      -- 5, 10, 12, 15 o 20 por ronda
    ADD COLUMN modo_memes TINYINT NOT NULL DEFAULT 2;        -- 2 = dos momos, 1 = un solo momo

-- Cuantos rerolls lleva gastados cada jugador en su momo
ALTER TABLE memes
    ADD COLUMN rerolls_usados TINYINT NOT NULL DEFAULT 0;
