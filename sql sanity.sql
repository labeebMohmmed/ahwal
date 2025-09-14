/* Ensure ReqID + index + optional audit columns exist */
IF COL_LENGTH('dbo.TableAddModel', 'ReqID') IS NULL
BEGIN
ALTER TABLE dbo.TableAddModel ADD ReqID INT NULL;
END


IF NOT EXISTS (
SELECT 1 FROM sys.indexes i
JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE i.object_id = OBJECT_ID('dbo.TableAddModel')
AND i.name = 'IX_TableAddModel_ReqID'
AND c.name = 'ReqID'
)
BEGIN
CREATE INDEX IX_TableAddModel_ReqID ON dbo.TableAddModel(ReqID);
END


/* Optional audit columns */
IF COL_LENGTH('dbo.TableAddModel', 'UpdatedAt') IS NULL
ALTER TABLE dbo.TableAddModel ADD UpdatedAt DATETIME2(3) NULL;
IF COL_LENGTH('dbo.TableAddModel', 'UpdatedBy') IS NULL
ALTER TABLE dbo.TableAddModel ADD UpdatedBy NVARCHAR(128) NULL;


IF COL_LENGTH('dbo.TableProcReq', 'UpdatedAt') IS NULL
ALTER TABLE dbo.TableProcReq ADD UpdatedAt DATETIME2(3) NULL;
IF COL_LENGTH('dbo.TableProcReq', 'UpdatedBy') IS NULL
ALTER TABLE dbo.TableProcReq ADD UpdatedBy NVARCHAR(128) NULL;


/* Ensure TableProcReq has an index/PK on ID */
IF NOT EXISTS (
SELECT 1 FROM sys.indexes WHERE name = 'PK_TableProcReq_ID' AND object_id = OBJECT_ID('dbo.TableProcReq')
)
BEGIN
IF COL_LENGTH('dbo.TableProcReq', 'ID') IS NOT NULL
BEGIN
BEGIN TRY
ALTER TABLE dbo.TableProcReq ADD CONSTRAINT PK_TableProcReq_ID PRIMARY KEY CLUSTERED (ID);
END TRY BEGIN CATCH END CATCH;
END
END