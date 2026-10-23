<?php
// The 6 documents (all point to sample.pdf for now)
$docs = [
  'attachment_letter'  => 'sample.pdf',
  'application_letter' => 'sample.pdf',
  'cv'                 => 'sample.pdf',
  'good_conduct'       => 'sample.pdf',
  'medical_insurance'  => 'sample.pdf',
  'id_passport'        => 'sample.pdf',
];

$applications = [
  ['id'=>1,'name'=>'Brian Otieno','reg_no'=>'CSC6/0898/25','email'=>'brian@gmail.com','phone'=>'0712 345 678',
   'institution'=>'KiNaP','course'=>'Computer Science','year'=>'Year 2',
   'department'=>'ICT','duration'=>'3 months','preferred_start'=>'2026-11-01','date'=>'2026-10-10',
   'skills'=>'PHP, Java, MySQL',
   'letter'=>'I am passionate about software development and would like to gain hands-on experience in your ICT department.',
   'docs'=>$docs],

  ['id'=>2,'name'=>'Mercy Wanjiku','reg_no'=>'IT/0345/24','email'=>'mercy@gmail.com','phone'=>'0723 456 789',
   'institution'=>'JKUAT','course'=>'IT','year'=>'Year 3',
   'department'=>'Broadcasting','duration'=>'3 months','preferred_start'=>'2026-11-15','date'=>'2026-10-12',
   'skills'=>'Video editing, Networking',
   'letter'=>'I would like to apply my IT skills while learning how technology supports broadcasting.',
   'docs'=>$docs],

  ['id'=>3,'name'=>'Kevin Mwangi','reg_no'=>'ELE/0567/25','email'=>'kevin@gmail.com','phone'=>'0734 567 890',
   'institution'=>'KiNaP','course'=>'Electronics','year'=>'Year 2',
   'department'=>'Broadcasting','duration'=>'2 months','preferred_start'=>'2026-12-01','date'=>'2026-10-15',
   'skills'=>'Circuit design, Audio equipment',
   'letter'=>'I am interested in practical work with broadcasting and studio equipment.',
   'docs'=>$docs],
];

$attachees = [
  ['name'=>'Faith Achieng','supervisor'=>'Mr. Kamau','start'=>'2026-08-01','end'=>'2026-11-01'],
  ['name'=>'Daniel Kiprop','supervisor'=>'Ms. Njeri','start'=>'2026-09-15','end'=>'2026-12-15'],
];

$supervisors = [
  ['name'=>'Mr. Kamau','department'=>'ICT','email'=>'kamau@company.co.ke','count'=>1],
  ['name'=>'Ms. Njeri','department'=>'Broadcasting','email'=>'njeri@company.co.ke','count'=>1],
];

// Progress helper
function progress($start, $end) {
  $s = strtotime($start); $e = strtotime($end);
  $pct  = max(0, min(100, round((time() - $s) / ($e - $s) * 100)));
  $left = max(0, ceil(($e - time()) / 86400));
  return [$pct, $left];
}