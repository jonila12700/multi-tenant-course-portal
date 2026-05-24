<?php
require_once 'includes/session.php';
require_once 'includes/paths.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo Guide - Course Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset_path('assets/css/app.css')); ?>" rel="stylesheet">
</head>
<body>
    <main class="content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="mb-1">Demo Guide</h1>
                    <p class="text-muted mb-0">Use this flow to present the multi-tenant course portal live.</p>
                </div>
                <a href="login.php" class="btn btn-primary">Open Login</a>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header">1. Admin Setup</div>
                        <div class="card-body">
                            <p>Login as admin or register a new organization.</p>
                            <ul>
                                <li>Copy the Class Signup Code.</li>
                                <li>Create or review courses.</li>
                                <li>Enroll students into courses.</li>
                                <li>Open Reports for monitoring.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header">2. Professor Flow</div>
                        <div class="card-body">
                            <p>Professor joins with the organization code.</p>
                            <ul>
                                <li>Create course materials.</li>
                                <li>Post course announcements.</li>
                                <li>Open a live quiz.</li>
                                <li>Watch progress and results update.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header">3. Student Flow</div>
                        <div class="card-body">
                            <p>Students join with the same organization code.</p>
                            <ul>
                                <li>View enrolled courses.</li>
                                <li>Read announcements.</li>
                                <li>Answer live quizzes.</li>
                                <li>Mark resources as completed.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Live Features to Show</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-md-0">
                                <li>Online users appear in Live Center.</li>
                                <li>Live chat works between logged-in users.</li>
                                <li>Announcements trigger live notifications.</li>
                                <li>Resource uploads notify enrolled students.</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li>Live quiz results update automatically.</li>
                                <li>Admin reports show platform activity.</li>
                                <li>Professor progress page tracks completed resources.</li>
                                <li>Responsive layout works on laptop and phone.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <a href="login.php" class="btn btn-primary">Login</a>
                <a href="join.php" class="btn btn-secondary">Join Organization</a>
                <a href="register.php" class="btn btn-outline-primary">Register Organization</a>
            </div>
        </div>
    </main>
</body>
</html>
