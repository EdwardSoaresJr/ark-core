# RTE labor guide schema
## JobLvl3 (JOB_LVL3.BTR)
- lvl123: type=11, offset=0, size=7, dec=0
- mod_eng_code: type=11, offset=7, size=7, dec=0
- Job_id_code: type=11, offset=14, size=5, dec=0
- exception: type=11, offset=19, size=1, dec=0

## JobTbl (JOB_TBL.BTR)
- jobid: type=11, offset=0, size=5, dec=0
- mod_eng_code: type=11, offset=5, size=7, dec=0
- exception: type=11, offset=12, size=1, dec=0

## carlvl3 (CAR_LVL3.BTR)
- lvl123_code: type=11, offset=0, size=7, dec=0
- car_id_code: type=11, offset=7, size=7, dec=0
- car_desc: type=11, offset=14, size=39, dec=0
- lo_yr: type=11, offset=53, size=5, dec=0
- hi_yr: type=11, offset=58, size=5, dec=0

## engtbl (ENGTBL.BTR)
- mod_id_code: type=11, offset=0, size=7, dec=0
- eng_id_code: type=11, offset=7, size=7, dec=0
- eng_desc: type=11, offset=14, size=39, dec=0
- lo_yr: type=11, offset=53, size=5, dec=0
- hi_yr: type=11, offset=58, size=5, dec=0

## job_lku (JOB_LKU.BTR)
- job_id_code: type=11, offset=0, size=7, dec=0
- job_desc: type=11, offset=7, size=39, dec=0
- eng_req: type=11, offset=46, size=1, dec=0

## lab (LAB.BTR)
- lab_id: type=11, offset=0, size=14, dec=0
- lo_yr: type=11, offset=14, size=5, dec=0
- hi_yr: type=11, offset=19, size=5, dec=0
- com_flag: type=11, offset=24, size=1, dec=0
- add_req: type=11, offset=25, size=1, dec=0
- model1: type=11, offset=26, size=7, dec=0
- model2: type=11, offset=33, size=7, dec=0
- model3: type=11, offset=40, size=7, dec=0
- model4: type=11, offset=47, size=7, dec=0
- model5: type=11, offset=54, size=7, dec=0
- model6: type=11, offset=61, size=7, dec=0
- model7: type=11, offset=68, size=7, dec=0
- model8: type=11, offset=75, size=7, dec=0
- model9: type=11, offset=82, size=7, dec=0
- eng1: type=11, offset=89, size=7, dec=0
- eng2: type=11, offset=96, size=7, dec=0
- eng3: type=11, offset=103, size=7, dec=0
- eng4: type=11, offset=110, size=7, dec=0
- eng5: type=11, offset=117, size=7, dec=0
- eng6: type=11, offset=124, size=7, dec=0
- eng7: type=11, offset=131, size=7, dec=0
- eng8: type=11, offset=138, size=7, dec=0
- eng9: type=11, offset=145, size=7, dec=0
- add_id1: type=11, offset=152, size=6, dec=0
- add_hr1: type=1, offset=158, size=4, dec=0
- add_id2: type=11, offset=162, size=6, dec=0
- add_hr2: type=1, offset=168, size=4, dec=0
- add_id3: type=11, offset=172, size=6, dec=0
- add_hr3: type=1, offset=178, size=4, dec=0
- add_id4: type=11, offset=182, size=6, dec=0
- add_hr4: type=1, offset=188, size=4, dec=0
- add_id5: type=11, offset=192, size=6, dec=0
- add_hr5: type=1, offset=198, size=4, dec=0
- add_id6: type=11, offset=202, size=6, dec=0
- add_hr6: type=1, offset=208, size=4, dec=0
- add_id7: type=11, offset=212, size=6, dec=0
- add_hr7: type=1, offset=218, size=4, dec=0
- add_id8: type=11, offset=222, size=6, dec=0
- add_hr8: type=1, offset=228, size=4, dec=0
- add_id9: type=11, offset=232, size=6, dec=0
- add_hr9: type=1, offset=238, size=4, dec=0
- hi_hr: type=14, offset=242, size=4, dec=0
- avg_hr: type=14, offset=246, size=4, dec=0
- lo_hr: type=14, offset=250, size=4, dec=0

## labcom (LABCOM.BTR)
- labid: type=11, offset=0, size=14, dec=0
- labidx: type=-1, offset=0, size=0, dec=0
- comment: type=12, offset=14, size=600, dec=0

