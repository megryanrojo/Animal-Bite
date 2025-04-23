<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Report Animal Bite</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f1f3f5;
    }
    .card {
      border-radius: 1rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    .form-label {
      font-weight: 500;
    }
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-10 col-lg-8">
        <div class="card p-4">
          <h3 class="text-center text-primary mb-4">Report Animal Bite Incident</h3>
          <form action="submit_report.php" method="POST" enctype="multipart/form-data">

            <!-- Patient Name -->
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="firstName" class="form-control" required />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="lastName" class="form-control" required />
              </div>
            </div>

            <!-- Birthdate and Sex -->
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Birth Date</label>
                <input type="date" name="birthDate" class="form-control" required />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Sex</label>
                <select class="form-select" name="sex" required>
                  <option value="" disabled selected>Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
            </div>

            <!-- Address -->
            <div class="mb-3">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2" required></textarea>
            </div>

            <!-- Contact Info (Optional) -->
            <div class="mb-3">
              <label class="form-label">Contact Number (Optional)</label>
              <input type="text" name="contactNumber" class="form-control" />
            </div>
            <div class="mb-3">
              <label class="form-label">Email (Optional)</label>
              <input type="email" name="email" class="form-control" />
            </div>

            <!-- Date of Incident -->
            <div class="mb-3">
              <label class="form-label">Date of Incident</label>
              <input type="date" name="incidentDate" class="form-control" required />
            </div>

            <!-- Bite Location -->
            <div class="mb-3">
              <label class="form-label">Bite Location on Body</label>
              <input type="text" name="biteLocation" class="form-control" placeholder="e.g. Left arm, neck" required />
            </div>

            <!-- Image Upload -->
            <div class="mb-3">
              <label class="form-label">Upload Image of Bite (Optional)</label>
              <input type="file" name="biteImage" class="form-control" accept="image/*" />
            </div>

            <!-- Additional Notes -->
            <div class="mb-3">
              <label class="form-label">Additional Notes / Symptoms</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Optional..."></textarea>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-primary w-100">Submit Report</button>

          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
