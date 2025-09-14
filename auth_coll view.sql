-- Create a small schema to keep office views tidy (safe if it already exists)
IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'office') EXEC('CREATE SCHEMA office');
GO

-- Unified list: TableAuth (توكيل) ∪ TableCollection (باقي المجموعات)
CREATE OR ALTER VIEW office.vw_OfficeUnified AS
SELECT
  N'Auth'                   AS OfficeTable,     -- which base table
  A.id                      AS OfficeId,
  A.[رقم_التوكيل]          AS OfficeNumber,    -- normalized
  N'توكيل'                 AS MainGroup,
  12                        AS MainGroupId,     -- per your rule
  A.[مقدم_الطلب]           AS ApplicantName,
  A.[رقم_الهوية]           AS IdNumber,
  A.[التاريخ_الميلادي]     AS [Date],
  A.[حالة_الارشفة]         AS ArchStatus,
  A.[طريقة_الطلب]          AS Method,
  -- lightweight tags for UI highlighting
  CASE LTRIM(RTRIM(A.[حالة_الارشفة]))
    WHEN N'جديد'          THEN 'status-new'
    WHEN N'قيد المراجعة'  THEN 'status-review'
    WHEN N'مؤرشف'         THEN 'status-archived'
    WHEN N'مرفوض'         THEN 'status-rejected'
    ELSE 'status-unknown'
  END AS StatusTag,
  CASE LTRIM(RTRIM(A.[طريقة_الطلب]))
    WHEN N'الكتروني' THEN 'method-online'
    WHEN N'حضوري'    THEN 'method-inperson'
    ELSE 'method-other'
  END AS MethodTag
FROM dbo.TableAuth AS A

UNION ALL

SELECT
  N'Collection'            AS OfficeTable,
  C.id                     AS OfficeId,
  C.[رقم_المعاملة]        AS OfficeNumber,
  N'غير ذلك'              AS MainGroup,        -- other main groups
  10                       AS MainGroupId,     -- per your rule
  C.[مقدم_الطلب]          AS ApplicantName,
  C.[رقم_الهوية]          AS IdNumber,
  C.[التاريخ_الميلادي]    AS [Date],
  C.[حالة_الارشفة]        AS ArchStatus,
  C.[طريقة_الطلب]         AS Method,
  CASE LTRIM(RTRIM(C.[حالة_الارشفة]))
    WHEN N'جديد'          THEN 'status-new'
    WHEN N'قيد المراجعة'  THEN 'status-review'
    WHEN N'مؤرشف'         THEN 'status-archived'
    WHEN N'مرفوض'         THEN 'status-rejected'
    ELSE 'status-unknown'
  END AS StatusTag,
  CASE LTRIM(RTRIM(C.[طريقة_الطلب]))
    WHEN N'الكتروني' THEN 'method-online'
    WHEN N'حضوري'    THEN 'method-inperson'
    ELSE 'method-other'
  END AS MethodTag
FROM dbo.TableCollection AS C;
GO


-- If you list/sort/filter often by التاريخ_الميلادي, حالة_الارشفة, طريقة_الطلب, مقدم_الطلب:
CREATE INDEX IX_TableAuth_List   ON dbo.TableAuth([التاريخ_الميلادي])  INCLUDE([مقدم_الطلب],[رقم_التوكيل],[رقم_الهوية],[حالة_الارشفة],[طريقة_الطلب]);
CREATE INDEX IX_TableColl_List   ON dbo.TableCollection([التاريخ_الميلادي]) INCLUDE([مقدم_الطلب],[رقم_المعاملة],[رقم_الهوية],[حالة_الارشفة],[طريقة_الطلب]);
