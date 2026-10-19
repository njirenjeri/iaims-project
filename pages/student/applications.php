<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

$ministries = [
	'Ministry of Agriculture and Livestock Development' => [
		'State Department for Agriculture',
		'State Department for Livestock Development',
	],
	'Ministry of Education' => [
		'State Department for Basic Education',
		'State Department for Higher Education and Research',
		'State Department for Technical, Vocational Education and Training',
	],
	'Ministry of Energy and Petroleum' => [
		'State Department for Energy',
		'State Department for Petroleum',
	],
	'Ministry of Environment, Climate Change and Forestry' => [
		'State Department for Environment and Climate Change',
		'State Department for Forestry',
	],
	'Ministry of Health' => [
		'State Department for Medical Services',
		'State Department for Public Health and Professional Standards',
	],
	'Ministry of Information, Communications and the Digital Economy' => [
		'State Department for Broadcasting and Telecommunications',
		'State Department for ICT and the Digital Economy',
	],
	'Ministry of Interior and National Administration' => [
		'State Department for Internal Security and National Administration',
		'State Department for Immigration and Citizen Services',
		'State Department for Correctional Services',
	],
	'Ministry of Labour and Social Protection' => [
		'State Department for Labour and Skills Development',
		'State Department for Social Protection and Senior Citizen Affairs',
	],
	'Ministry of Lands, Public Works, Housing and Urban Development' => [
		'State Department for Lands and Physical Planning',
		'State Department for Public Works',
		'State Department for Housing and Urban Development',
	],
	'Ministry of Public Service, Performance and Delivery Management' => [
		'State Department for Public Service',
		'State Department for Performance and Delivery Management',
	],
	'Ministry of Roads and Transport' => [
		'State Department for Roads',
		'State Department for Transport',
	],
	'Ministry of Water, Sanitation and Irrigation' => [
		'State Department for Water and Sanitation',
		'State Department for Irrigation',
	],
];

$qualificationOptions = ['Degree', 'Diploma', 'Certificate'];
$documents = [
	'attachment_letter' => 'Attachment letter',
	'cv' => 'Curriculum vitae (CV)',
	'medical_insurance' => 'Medical insurance',
	'application_letter' => 'Application letter',
	'identity_document' => 'National ID / Passport',
	'good_conduct' => 'Certificate of good conduct',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="Submit your industrial attachment application and required documents.">
	<title>Applications | <?= escapeHtml(APP_NAME) ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="<?= escapeHtml(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="profile-page">
	<nav class="navbar navbar-expand-lg profile-navbar">
		<div class="container">
			<a class="navbar-brand fw-semibold" href="<?= escapeHtml(BASE_URL) ?>/index.php?page=dashboard">
				<span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
				<span>IAIMS <small class="d-block fw-normal">Student Portal</small></span>
			</a>
			<a class="btn btn-outline-light btn-sm" href="<?= escapeHtml(BASE_URL) ?>/index.php?page=dashboard">
				<i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Dashboard
			</a>
		</div>
	</nav>

	<main class="container profile-container py-4 py-lg-5">
		<div class="profile-heading mb-4">
			<div>
				<p class="text-uppercase text-primary fw-semibold small mb-2">Industrial attachment</p>
				<h1 class="h2 fw-bold mb-2">Application form</h1>
				<p class="text-secondary mb-0">Enter your academic details, select your preferred government department, and attach all required documents.</p>
			</div>
		</div>

		<div class="alert alert-info" id="applicationFormStatus" role="status" hidden></div>

		<form id="applicationForm" class="needs-validation" novalidate>

			<section class="card profile-card mb-4" aria-labelledby="academic-heading">
				<div class="card-header profile-card-header">
					<span class="section-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
					<div><h2 class="h5 mb-1" id="academic-heading">Academic information</h2><p class="small text-secondary mb-0">Your current education and course details</p></div>
				</div>
				<div class="card-body p-3 p-md-4">
					<div class="row g-3">
						<div class="col-md-6">
							<label for="institution" class="form-label">Student institution <span class="text-danger">*</span></label>
							<div class="institution-lookup position-relative">
								<input class="form-control" id="institution" name="institution" maxlength="180" autocomplete="off" aria-autocomplete="list" aria-controls="institutionSuggestions" aria-expanded="false" required>
								<div id="institutionSuggestions" class="institution-suggestions list-group shadow-sm" role="listbox" hidden></div>
							</div>
							<div class="form-text" id="institutionLookupStatus" aria-live="polite">Start typing your Kenyan university name and choose a match from the directory.</div>
							<div class="invalid-feedback">Search for your institution and select a matching result.</div>
						</div>
						<div class="col-md-6">
							<label for="course_name" class="form-label">Course name <span class="text-danger">*</span></label>
							<input class="form-control" id="course_name" name="course_name" maxlength="180" required>
							<div class="invalid-feedback">Enter your course name.</div>
						</div>
						<div class="col-md-6">
							<label for="academic_qualification" class="form-label">Academic qualification <span class="text-danger">*</span></label>
							<select class="form-select" id="academic_qualification" name="academic_qualification" required>
								<option value="">Select qualification</option>
								<?php foreach ($qualificationOptions as $qualification): ?>
									<option value="<?= escapeHtml($qualification) ?>"><?= escapeHtml($qualification) ?></option>
								<?php endforeach; ?>
							</select>
							<div class="invalid-feedback">Select Degree, Diploma, or Certificate.</div>
						</div>
					</div>
				</div>
			</section>

			<section class="card profile-card mb-4" aria-labelledby="placement-heading">
				<div class="card-header profile-card-header">
					<span class="section-icon section-icon-gold"><i class="bi bi-building" aria-hidden="true"></i></span>
					<div><h2 class="h5 mb-1" id="placement-heading">Preferred placement</h2><p class="small text-secondary mb-0">Select the ministry and its state department</p></div>
				</div>
				<div class="card-body p-3 p-md-4">
					<div class="row g-3">
						<div class="col-md-6">
							<label for="ministry" class="form-label">Ministry <span class="text-danger">*</span></label>
							<select class="form-select" id="ministry" name="ministry" required>
								<option value="">Select ministry</option>
								<?php foreach (array_keys($ministries) as $ministry): ?>
									<option value="<?= escapeHtml($ministry) ?>"><?= escapeHtml($ministry) ?></option>
								<?php endforeach; ?>
							</select>
							<div class="invalid-feedback">Select a ministry.</div>
						</div>
						<div class="col-md-6">
							<label for="state_department" class="form-label">State department <span class="text-danger">*</span></label>
							<select class="form-select" id="state_department" name="state_department" required>
								<option value="">Select a ministry first</option>
							</select>
							<div class="invalid-feedback">Select a state department.</div>
						</div>
					</div>
				</div>
			</section>

			<section class="card profile-card mb-4" aria-labelledby="documents-heading">
				<div class="card-header profile-card-header">
					<span class="section-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span>
					<div><h2 class="h5 mb-1" id="documents-heading">Required documents</h2><p class="small text-secondary mb-0">Upload a separate PDF for each document (maximum 10 MB per file)</p></div>
				</div>
				<div class="card-body p-3 p-md-4">
					<div class="row g-3">
						<?php foreach ($documents as $field => $label): ?>
							<div class="col-md-6">
								<label for="<?= escapeHtml($field) ?>" class="form-label"><?= escapeHtml($label) ?> <span class="text-danger">*</span></label>
								<input class="form-control application-file" type="file" id="<?= escapeHtml($field) ?>" name="<?= escapeHtml($field) ?>" accept="application/pdf,.pdf" data-label="<?= escapeHtml($label) ?>" required>

								<div class="invalid-feedback">Choose a PDF file no larger than 10 MB.</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="alert alert-light border mt-4 mb-0 small" role="note">
						<i class="bi bi-info-circle me-1" aria-hidden="true"></i> Frontend preview only: files are checked in your browser and are not uploaded or saved.
					</div>
				</div>
			</section>

			<div class="profile-form-footer d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
				<p class="small text-secondary mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>All fields and documents are required to submit your application.</p>
				<button type="submit" class="btn btn-profile-save btn-lg px-4">
					<i class="bi bi-send me-2" aria-hidden="true"></i>Submit application
				</button>
			</div>
		</form>

		<p class="text-center text-secondary small mt-4 mb-0">&copy; <?= date('Y') ?> <?= escapeHtml(APP_NAME) ?></p>
	</main>

	<script>
		window.applicationDepartments = <?= json_encode($ministries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
	</script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="<?= escapeHtml(BASE_URL) ?>/assets/js/applications.js"></script>
</body>
</html>