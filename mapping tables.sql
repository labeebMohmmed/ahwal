{"applicants":[{"role":"primary","name":"خالد محمد احمد محمود","sex":"M","job":"عامل","nationality":"سوداني","residenceStatus":"مقيم","dob":"2000-06-16","ids":[{"type":"جواز سفر","number":"P01256363","issuer":"الرياض","expiry":"2025-10-11"}]}],
"authenticated":[{"name":"نعمات محمد احمد محمود","sex":"F","nationality":"سوداني","ids":[{"type":"جواز سفر","number":"P01256353"}]}],"witnesses":[{"name":"الشيخ محمد احمد محمود","sex":"M","ids":[{"type":"جواز سفر","number":"P01253333"}]},{"name":"احمد محمد احمد محمود","sex":"M","ids":[{"type":"جواز سفر","number":"P01253222"}]}],"contact":{"phone":"","email":""}}
{"model":{"id":62,"mainGroup":"توكيل","altColName":"زواج","altSubColName":"استخراج قسيمة زواج","langLabel":"العربية"},"answers":{"fields":{"itext1":"ريم ايوب عبدالرحمن خالد","icheck1":"زوجتي","itxtDate1":"2025-07-29"},"_touchedAt":"2025-09-11T09:32:52+00:00"},"requirements":{"needAuthenticated":true,"needWitnesses":true,"needWitnessesOptional":false}}
SELECT TOP (1000) [ID]
      ,[رقم_التوكيل]
      ,[مقدم_الطلب]                 -- applicants: name1, name2, name3, ....
      ,[النوع]                      -- applicants: sex1, sex2, sex3, ....
      ,[نوع_الهوية]                 -- applicants: ids.type1, ids.type2, ids.type3, ....
      ,[رقم_الهوية]                 -- applicants: ids.number1, ids.number2, ids.number3, ....
      ,[مكان_الإصدار]                -- applicants: ids.issuer1, ids.issuer1, ids.issuer3, ....
      ,[الموكَّل]                     -- authenticated: name1, name2, name3, ....
      ,[جنس_الموكَّل]                 -- authenticated: sex1, sex2, sex3, ....
      ,[نوع_التوكيل]                -- model: altColName
      ,[رقم_العمود]                 -- skip
      ,[موضوع_التوكيل]              -- skip
      ,[اضافة_الموضوع]              -- skip
      ,[حقوق_التوكيل]               -- skip
      ,[التاريخ_الميلادي]            -- UpdatedAt
      ,[التاريخ_الهجري]             -- skip
      ,[موقع_التوكيل]               -- skip
      ,[المعالجة]                   -- skip
      ,[طريقة_الطلب]                -- method-online
      ,[اسم_الموظف]                 -- skip
      ,[اسم_المندوب]                -- skip
      ,[مكان_الاستخدام]              -- skip
      ,[الشاهد_الأول]                -- witnesses: name1
      ,[هوية_الأول]                  -- witnesses: ids.number1
      ,[الشاهد_الثاني]              -- witnesses: name2
      ,[هوية_الثاني]                -- witnesses: ids.number2
      ,[ارشفة_المستندات]            -- skip
      ,[المكاتبة_النهائية]          -- skip
      ,[تعليق]                      -- skip
      ,[إجراء_التوكيل]              -- model: altSubColName
      ,[specialData]                -- skip
      ,[حالة_الارشفة]                -- status-archived
      ,[المكاتبات_الملغية]          -- skip
      ,[توكيل_مرجعي]                -- skip
      ,[fileUpload]                 -- skip
      ,[itext1]                     -- answers.fields.itext1
      ,[itext2]                     -- answers.fields.itext2
      ,[itext3]                     -- answers.fields.itext3
      ,[itext4]                     -- answers.fields.itext4
      ,[itext5]                     -- answers.fields.itext5
      ,[icheck1]                    -- answers.fields.check1
      ,[itxtDate1]                  -- answers.fields.itxtDate1
      ,[itxtDate2]                  -- answers.fields.itxtDate2
      ,[icombo1]                    -- answers.fields.icombo1
      ,[ibtnAdd1]                   -- answers.fields.ibtnAdd1
      ,[icombo2]                    -- answers.fields.icombo2
      ,[الاعدادات]                   -- skip
      ,[تاريخ_الميلاد]               -- UpdatedAt
      ,[المهنة]                     -- applicants: job1, job2, job3, ....
      ,[جنسية_الموكل]               -- authenticated: name1, name2, name3, ....
      ,[هوية_الموكل]                -- authenticated: nationality1, nationality2, nationality3, ...
      ,[removedDocNo]               -- skip
      ,[قائمة_الحقوق]               -- answers.rightsText
      ,[txtReview]                  -- answers.textModel
      ,[combTitle13]                -- skip
      ,[combTitle14]                -- skip
      ,[طريقة_الإجراء]               -- skip
      ,[اسم_الموكل_بالتوقيع]        -- skip
      ,[نوع_الموقع]                 -- skip
      ,[رقم_الوكالة]                -- skip
      ,[جهة_إصدار_الوكالة]          -- skip
      ,[تاريخ_إصدار_الوكالة]        -- skip
      ,[العدد]                      -- skip
      ,[removedDocSource]           -- skip   
      ,[removedDocDate]             -- skip
      ,[تاريخ_الارشفة1]              -- UpdatedAt
      ,[تاريخ_الارشفة2]              -- UpdatedAt
      ,[archFirm]                   -- skip
      ,[رقم_المرجع_المرتبط_off]     -- skip
      ,[الإجراء_الأخير]               -- skip
      ,[نوع_هوية]                   -- authenticated: ids.type1, ids.type2, ids.type3, ....
      ,[startTime]                  -- skip
      ,[endTime]                    -- skip
      ,[وضع_الإقامة]                 -- skip
      ,[انتهاء_الصلاحية]             -- applicants: ids.expiry1, ids.expiry2, ids.expiry3, ....
      ,[الصفة_القانونية]            -- legalStatusText
      ,[الأهلية]                     -- skip
      ,[حالة_السداد]                -- skip
      ,[توضيح_التجاوز]              -- skip
      ,[الرقم_المرجعي]              -- skip
      ,[صفة_الموقع]                 -- skip
      ,[itext6]                     -- answers.fields.itext6
      ,[itext7]                     -- answers.fields.itext7
      ,[itext8]                     -- answers.fields.itext8
      ,[itext9]                     -- answers.fields.itext9
      ,[itext10]                    -- answers.fields.itext10
      ,[الخاتمة]                    -- answers.fields.itext10
      ,[الوجهة]                     -- answers.fields.itext10
      ,[اللغة]                      -- Lang
      ,[نوع_المكاتبة]               -- skip
      ,[مدة_الاعتماد]                -- answers.footer
      ,[التوثيق]                    -- answers.authentication_text
      ,[نص_المعاملة]                -- skip
      ,[موقع_المعاملة]              -- skip
      ,[activity]                   -- skip
  FROM [AhwalDataBase].[dbo].[TableAuth] where id = 26782



  INSERT INTO dbo.TableAuth (
  مقدم_الطلب,     النوع,                 المهنة,                  تاريخ_الميلاد,
  نوع_الهوية,                رقم_الهوية,                 مكان_الإصدار,           انتهاء_الصلاحية,
  الموكَّل,        جنس_الموكَّل,          جنسية_الموكل,           نوع_هوية,                 هوية_الموكل,
  الشاهد_الأول,    هوية_الأول,            الشاهد_الثاني,           هوية_الثاني,
  موضوع_التوكيل,   اضافة_الموضوع,        قائمة_الحقوق,            موقع_التوكيل,
  الرقم_المرجعي,   اللغة,                 نوع_التوكيل,             إجراء_التوكيل,            تعليق
) VALUES (
  N'أحمد علي محمد | سارة محمد عبدالله',
  N'ذكر | أنثى',
  N'مهندس | محاسبة',
  N'1990-03-15 | 1995-06-20',

  N'جواز سفر | بطاقة قومية',
  N'P01234567 | 1234567890',
  N'الرياض | الخرطوم',
  N'2027-12-31 | 2028-05-30',

  N'محمد عبد الله صالح | آمنة دفع الله حسن',
  N'ذكر | أنثى',
  N'سوداني | سودانية',
  N'جواز سفر | بطاقة قومية',
  N'P09876543 | 9876543210',

  N'الشيخ حسن علي', N'P1111111',
  N'إبراهيم يوسف',  N'P2222222',

  N'تفويض لإكمال معاملات الأسرة',
  N'متابعة أمام الجهات المختصة',
  N'1_1_0_1_0_1_0_1',
  N'القنصلية – الرياض',

  N'4507A827',
  N'ar',
  N'زواج',
  N'إجراءات عامة',
  N'يوجد أكثر من مقدم وموكَّل في هذه المعاملة'
);

ALTER TABLE dbo.TableAuth
ALTER COLUMN [الرقم_المرجعي] NVARCHAR(200) NULL