-- Verwijder unieke constraint op residents.house_number zodat max 2 accounts per huisnummer mogelijk wordt.
ALTER TABLE residents DROP INDEX IF EXISTS house_number;
ALTER TABLE residents DROP INDEX IF EXISTS residents_house_number_unique;

-- Houd zoekprestaties op huisnummer goed.
CREATE INDEX IF NOT EXISTS idx_residents_house_number ON residents (house_number);
