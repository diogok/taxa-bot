
CREATE TABLE IF NOT EXISTS taxa (
    `taxonID` VARCHAR(250),
    `family` VARCHAR(250),
    `genus` VARCHAR(250),
    `scientificName` VARCHAR(250),
    `scientificNameAuthorship` VARCHAR(250),
    `scientificNameWithoutAuthorship` VARCHAR(250),
    `taxonomicStatus` VARCHAR(250),
    `acceptedNameUsage` VARCHAR(250),
    `taxonRank`VARCHAR(250),
    `higherClassification` VARCHAR(250),
    `source` VARCHAR(5000),
    PRIMARY KEY ( `taxonID` )
);

