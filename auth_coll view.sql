ALTER VIEW office.vw_OfficeUnified AS
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
  CASE 
    WHEN A.[مقدم_الطلب] IS NULL          THEN N'معاملة جديدة'
    WHEN A.[مقدم_الطلب] IS NOT NULL AND A.[حالة_الارشفة] <> N'مؤرشف نهائي'   THEN N'قيد المعالجة'
    WHEN A.[حالة_الارشفة] = N'مؤرشف نهائي'   THEN N'مؤرشف نهائي'    
    ELSE N'غير مؤرشف'
  END AS StatusTag,
  
  CASE 
    WHEN A.[طريقة_الطلب] = N'الكتروني' THEN N'اونلاين'
    WHEN A.[طريقة_الطلب] = N'عن طريق أحد مندوبي القنصلية'    THEN N'بواسطة مندوب'
    ELSE N'حضور مباشر'
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
  CASE 
    WHEN C.[مقدم_الطلب] IS NULL          THEN N'معاملة جديدة'
    WHEN C.[مقدم_الطلب] IS NOT NULL AND C.[حالة_الارشفة] <> N'مؤرشف نهائي'   THEN N'قيد المعالجة'
    WHEN C.[حالة_الارشفة] = N'مؤرشف نهائي'   THEN N'مؤرشف نهائي'    
    ELSE N'غير مؤرشف'

  END AS StatusTag,
  CASE 
    WHEN C.[طريقة_الطلب] = N'الكتروني' THEN N'اونلاين'
    WHEN C.[طريقة_الطلب] = N'عن طريق أحد مندوبي القنصلية'    THEN N'بواسطة مندوب'
    ELSE N'حضور مباشر'
  END AS MethodTag
FROM dbo.TableCollection AS C;
GO

