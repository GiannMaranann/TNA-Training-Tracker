<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Individual Development Plan</title>
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#2563eb',
        secondary: '#3b82f6',
        accent: '#1d4ed8',
        light: '#f8fafc',
        dark: '#1e293b',
        sidebar: '#1e40af',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444'
      },
      borderRadius: {
        'none': '0px',
        'sm': '4px',
        DEFAULT: '8px',
        'md': '12px',
        'lg': '16px',
        'xl': '20px',
        '2xl': '24px',
        '3xl': '32px',
        'full': '9999px',
        'button': '8px'
      },
      fontFamily: {
        'sans': ['Poppins', 'sans-serif'],
        'display': ['Poppins', 'sans-serif']
      },
      boxShadow: {
        'soft': '0 4px 20px rgba(0, 0, 0, 0.08)',
        'focus': '0 0 0 3px rgba(37, 99, 235, 0.2)',
        'card': '0 2px 8px rgba(0, 0, 0, 0.1)'
      }
    }
  }
}
</script>

<style>
  body {
    background-color: #f1f5f9;
    color: #334155;
    overflow-x: hidden;
  }
  
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 16rem;
    overflow-y: auto;
    transition: all 0.3s;
    z-index: 50;
  }
  
  .main-content {
    margin-left: 16rem;
    transition: all 0.3s;
  }
  
  .checkbox-custom {
    appearance: none;
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    background-color: white;
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  
  .checkbox-custom:checked {
    background-color: #2563eb;
    border-color: #2563eb;
  }
  
  .checkbox-custom:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 0.75rem;
    font-weight: bold;
  }
  
  input[type="text"], input[type="date"], textarea, select {
    border: 1px solid #e2e8f0;
    padding: 0.75rem;
    border-radius: 6px;
    background-color: white;
    transition: all 0.2s ease;
    font-size: 0.95rem;
    width: 100%;
  }
  
  input[type="text"]:focus, input[type="date"]:focus, textarea:focus, select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
  }
  
  table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  }
  
  th, td {
    padding: 1rem;
    text-align: left;
    vertical-align: top;
    font-size: 0.95rem;
    line-height: 1.5;
    border: 1px solid #e2e8f0;
  }
  
  th {
    background-color: #f8fafc;
    font-weight: 600;
    color: #1e293b;
    position: sticky;
    top: 0;
  }
  
  td {
    background-color: white;
  }
  
  tr:hover td {
    background-color: #f8fafc;
  }
  
  .signature-line {
    border-bottom: 1px solid #64748b;
    min-height: 2.5rem;
    margin-bottom: 0.75rem;
    position: relative;
  }
  
  .signature-line::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 1px;
    background: #64748b;
    opacity: 0.3;
  }
  
  .section-header {
    position: relative;
    padding-left: 1.5rem;
  }
  
  .section-header::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #2563eb;
    border-radius: 4px;
  }
  
  .floating-label {
    position: relative;
    margin-bottom: 1.5rem;
  }
  
  .floating-label input, .floating-label textarea, .floating-label select {
    padding-top: 1.5rem;
  }
  
  .floating-label label {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    font-size: 0.75rem;
    color: #64748b;
    transition: all 0.2s ease;
    pointer-events: none;
  }
  
  .floating-label input:focus + label,
  .floating-label input:not(:placeholder-shown) + label,
  .floating-label textarea:focus + label,
  .floating-label textarea:not(:placeholder-shown) + label {
    transform: translateY(-0.5rem) scale(0.85);
    transform-origin: left top;
    color: #2563eb;
  }
  
  .print-only {
    display: none;
  }
  
  @media print {
    .no-print {
      display: none;
    }
    .print-only {
      display: block;
    }
    body {
      background-color: white;
    }
    main {
      box-shadow: none;
      margin: 0;
      padding: 0;
    }
    th {
      background-color: #f8fafc !important;
      -webkit-print-color-adjust: exact;
    }
  }
  
  /* Submitted forms card styling */
  .submitted-form-card {
    transition: all 0.3s ease;
    border-left: 4px solid #2563eb;
  }
  
  .submitted-form-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
  
  /* Modal backdrop */
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 100;
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
  }
  
  .modal-backdrop.active {
    opacity: 1;
    pointer-events: all;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 0.5rem;
    width: 90%;
    max-width: 500px;
    transform: translateY(20px);
    transition: transform 0.3s ease;
  }
  
  .modal-backdrop.active .modal-content {
    transform: translateY(0);
  }
</style>
</head>
<body class="min-h-screen font-sans flex">
<!-- Fixed Sidebar -->
<aside class="sidebar bg-blue-900 text-white shadow-sm flex flex-col justify-between">
  <div class="h-full flex flex-col">
    <!-- Logo & Title -->
    <div class="p-6 flex items-center">
      <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2" />
      <a href="user_page.php" class="text-lg font-semibold text-white">Training Needs Assessment</a>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-6">
      <div class="space-y-2">
        <a href="user_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-dashboard-line"></i></div>
          TNA
        </a>
        <a href="idp_form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-text-line"></i></div>
          IDP Form
        </a>
        <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">  
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-user-line"></i></div>
          Profile
        </a>
      </div>
    </nav>

    <!-- Sign Out -->
    <div class="p-4">
      <a href="homepage.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md hover:bg-red-600 text-white">
        <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-logout-box-line"></i></div>
        Sign Out
      </a>
    </div>
  </div>
</aside>

<!-- Main Content -->
<div class="flex-1 main-content">
  <div class="container mx-auto px-4 py-8">
    <!-- Submitted Forms Section (Collapsible) -->
    <div class="mb-8">
      <div class="flex items-center justify-between mb-4 cursor-pointer" id="submitted-forms-toggle">
        <h2 class="text-xl font-bold text-dark flex items-center">
          <i class="fas fa-file-alt mr-2 text-primary"></i> Submitted IDP Forms
          <span class="ml-2 text-sm bg-primary text-white px-2 py-1 rounded-full" id="submitted-count">0</span>
        </h2>
        <i class="fas fa-chevron-down transition-transform duration-300" id="submitted-forms-arrow"></i>
      </div>
      
      <div class="bg-white rounded-lg shadow-soft overflow-hidden hidden" id="submitted-forms-container">
        <div class="p-4 border-b border-gray-200">
          <div class="relative">
            <input type="text" placeholder="Search submitted forms..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
          </div>
        </div>
        
        <div class="divide-y divide-gray-200" id="submitted-forms-list">
          <!-- Submitted forms will be dynamically added here -->
          <div class="p-4 text-center text-gray-500" id="no-forms-message">
            No submitted forms yet. Complete and submit your first IDP form to see it here.
          </div>
        </div>
      </div>
    </div>
    
    <!-- Current Form -->
    <main class="bg-white shadow-soft rounded-xl overflow-hidden">
      <div class="p-8 md:p-12">
        <!-- Header Section -->
        <div class="text-center mb-10">
          <div class="flex items-center justify-center mb-4">
            <div class="mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
              </svg>
            </div>
            <div>
              <h1 class="text-3xl font-bold text-primary">INDIVIDUAL DEVELOPMENT PLAN</h1>
              <p class="text-sm text-gray-500 mt-1">Employee Growth and Competency Roadmap</p>
            </div>
          </div>
          <div class="w-24 h-1.5 bg-gradient-to-r from-primary to-secondary mx-auto rounded-full"></div>
        </div>
        
        <!-- Form Actions -->
        <div class="flex justify-between items-center mb-6">
          <div>
            <span class="text-sm font-medium text-gray-600">Form Status:</span>
            <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800" id="form-status">Draft</span>
          </div>
          <div class="flex space-x-3">
            <button id="save-draft-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center">
              <i class="fas fa-save mr-2"></i> Save Draft
            </button>
            <button id="submit-form-btn" class="bg-primary hover:bg-accent text-white px-4 py-2 rounded-lg flex items-center">
              <i class="fas fa-paper-plane mr-2"></i> Submit Form
            </button>
          </div>
        </div>
        
        <!-- Personal Information Section -->
        <div class="mb-10">
          <h3 class="text-xl font-bold mb-6 text-dark flex items-center">
            <i class="fas fa-user-circle mr-2 text-primary"></i> Personal Information
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Column 1 -->
            <div class="space-y-6">
              <div class="floating-label">
                <input type="text" id="name" placeholder=" " class="border border-gray-200">
                <label for="name">Name</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="position" placeholder=" " class="border border-gray-200">
                <label for="position">Current Position</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="salary-grade" placeholder=" " class="border border-gray-200">
                <label for="salary-grade">Salary Grade</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="years-position" placeholder=" " class="border border-gray-200">
                <label for="years-position">Years in this Position</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="years-lspu" placeholder=" " class="border border-gray-200">
                <label for="years-lspu">Years in LSPU</label>
              </div>
            </div>
            
            <!-- Column 2 -->
            <div class="space-y-6">
              <div class="floating-label">
                <input type="text" id="years-other" placeholder=" " class="border border-gray-200">
                <label for="years-other">Years in Other Office/Agency if any</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="division" placeholder=" " class="border border-gray-200">
                <label for="division">Division</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="office" placeholder=" " class="border border-gray-200">
                <label for="office">Office</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="address" placeholder=" " class="border border-gray-200">
                <label for="address">Office Address</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="supervisor" placeholder=" " class="border border-gray-200">
                <label for="supervisor">Supervisor's Name</label>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Purpose Section -->
        <div class="mb-10">
          <h3 class="text-xl font-bold mb-6 text-dark flex items-center">
            <i class="fas fa-bullseye mr-2 text-primary"></i> Purpose
          </h3>
          
          <div class="bg-blue-50 p-6 rounded-lg">
            <div class="space-y-3">
              <div class="flex items-start">
                <input type="checkbox" id="purpose1" class="checkbox-custom mt-1 mr-3">
                <label for="purpose1" class="text-sm text-gray-700 flex-1">To meet the competencies in the current positions</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose2" class="checkbox-custom mt-1 mr-3">
                <label for="purpose2" class="text-sm text-gray-700 flex-1">To increase the level of competencies of current positions</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose3" class="checkbox-custom mt-1 mr-3">
                <label for="purpose3" class="text-sm text-gray-700 flex-1">To meet the competencies in the next higher position</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose4" class="checkbox-custom mt-1 mr-3">
                <label for="purpose4" class="text-sm text-gray-700 flex-1">To acquire new competencies across different functions/position</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose5" class="checkbox-custom mt-1 mr-3">
                <div class="flex-1">
                  <label for="purpose5" class="text-sm text-gray-700">Others, please specify:</label>
                  <input type="text" id="purpose-other" class="mt-1 w-full border-b border-gray-300 bg-transparent focus:border-primary focus:outline-none" placeholder="Specify here">
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Career Development Section -->
        <div class="mb-10">
          <h3 class="text-xl font-bold mb-6 text-dark flex items-center">
            <i class="fas fa-chart-line mr-2 text-primary"></i> Career Development
          </h3>
          
          <!-- Long Term Goals -->
          <div class="mb-10">
            <h4 class="font-bold mb-4 text-primary flex items-center">
              <i class="fas fa-calendar-alt mr-2"></i> Training/Development Interventions for Long Term Goals (Next Five Years)
            </h4>
            
            <div class="overflow-x-auto">
              <table class="w-full rounded-lg overflow-hidden" id="long-term-goals">
                <thead>
                  <tr>
                    <th class="w-1/4">Area of Development</th>
                    <th class="w-1/4">Development Activity</th>
                    <th class="w-1/4">Target Completion Date</th>
                    <th class="w-1/4">Completion Stage</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Academic (if applicable), Attendance to seminar on Supervisory Development, etc"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Pursuance of Academic Degrees for advancement"></td>
                    <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Enter stage"></td>
                  </tr>
                  <tr>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Enter area"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Enter activity"></td>
                    <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Enter stage"></td>
                  </tr>
                </tbody>
              </table>
              <button type="button" class="mt-2 text-primary hover:text-accent text-sm font-medium flex items-center" id="add-long-term-row">
                <i class="fas fa-plus-circle mr-1"></i> Add another row
              </button>
            </div>
          </div>
          
          <!-- Short Term Goals -->
          <div class="mb-10">
            <h4 class="font-bold mb-4 text-primary flex items-center">
              <i class="fas fa-calendar-day mr-2"></i> Short Term Development Goals Next Year
            </h4>
            
            <div class="overflow-x-auto">
              <table class="w-full rounded-lg overflow-hidden" id="short-term-goals">
                <thead>
                  <tr>
                    <th>Area of Development</th>
                    <th>Priority for Learning or Development Program (LDP)</th>
                    <th>Development Activity</th>
                    <th>Target Completion Date</th>
                    <th>Who is Responsible</th>
                    <th>Completion Stage</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="1. Behavioral Training such as: Value Re-orientation, Team Building, Oral Communication, Interpersonal Skills, Customer Relations, People Development, Improving Planning & Delivery, Solving Problems and making decisions, Leadership and Supervision Program, etc" readonly></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Priority"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="Conduct of training/seminar"></td>
                    <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Responsible person"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Stage"></td>
                  </tr>
                  <tr>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="2. Technical Skills Training such as: Basic Occupational Safety & Health, Office Management Procedures, Preventive Maintenance Activities, etc" readonly></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Priority"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Activity"></td>
                    <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Responsible person"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Stage"></td>
                  </tr>
                  <tr>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="3. Quality Management Training such as: Customer Requirements, Time Management, Continuous Improvement on Quality & Productivity, etc" readonly></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Priority"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Activity"></td>
                    <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Responsible person"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Stage"></td>
                  </tr>
                  <tr>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="4. Others: Formal Classroom Training, on-the-job training, Self Development, developmental assignment, etc" readonly></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Priority"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="Coaching on the job knowledge sharing and learning session"></td>
                    <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Responsible person"></td>
                    <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Stage"></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <!-- Certification Section -->
        <div class="mb-10">
          <h3 class="text-xl font-bold mb-6 text-dark flex items-center">
            <i class="fas fa-file-signature mr-2 text-primary"></i> Certification and Commitment
          </h3>
          
          <div class="bg-gray-50 p-6 rounded-lg mb-8">
            <p class="text-sm mb-4 text-gray-700">
              This is to certify that this Individual Development Plan has been discussed with me by my immediate superior. I further commit that I will exert time and effort to ensure that this will be achieved according to agreed time frames.
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-8">
              <div>
                <div class="signature-line"></div>
                <p class="text-center text-sm font-medium mt-2">Signature of Employee</p>
                <div class="text-center mt-2">
                  <input type="text" id="employee-name" class="text-center border-none border-b border-gray-300 bg-transparent w-3/4" placeholder="Printed Name">
                </div>
                <div class="text-center mt-2">
                  <input type="date" id="employee-date" class="text-center border-none border-b border-gray-300 bg-transparent w-3/4">
                </div>
              </div>
              
              <div>
                <div class="signature-line"></div>
                <p class="text-center text-sm font-medium mt-2">Immediate Supervisor</p>
                <div class="text-center mt-2">
                  <input type="text" id="supervisor-name" class="text-center border-none border-b border-gray-300 bg-transparent w-3/4" placeholder="Printed Name">
                </div>
                <div class="text-center mt-2">
                  <input type="date" id="supervisor-date" class="text-center border-none border-b border-gray-300 bg-transparent w-3/4">
                </div>
              </div>
              
              <div>
                <div class="signature-line"></div>
                <p class="text-center text-sm font-medium mt-2">Campus Director</p>
                <div class="text-center mt-2">
                  <input type="text" id="director-name" class="text-center border-none border-b border-gray-300 bg-transparent w-3/4" placeholder="Printed Name">
                </div>
                <div class="text-center mt-2">
                  <input type="date" id="director-date" class="text-center border-none border-b border-gray-300 bg-transparent w-3/4">
                </div>
              </div>
            </div>
            
            <div class="mt-8 text-center">
              <p class="text-sm text-gray-700">
                I commit for support and ensure that this agreed Individual Development Plan is achieved to the agreed time frames
              </p>
            </div>
          </div>
        </div>
        
        <!-- Footer -->
        <div class="flex flex-col md:flex-row justify-between items-center pt-6 border-t border-gray-200">
          <div class="mb-4 md:mb-0">
            <button id="print-btn" class="bg-primary hover:bg-accent text-white px-4 py-2 rounded-lg flex items-center no-print">
              <i class="fas fa-print mr-2"></i> Print Form
            </button>
          </div>
          <div class="text-sm text-gray-600 flex flex-col md:flex-row md:space-x-6">
            <span>LSPU-HRD-SF-027</span>
            <span>Rev. 1</span>
            <span>2 April 2018</span>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal-backdrop" id="confirmation-modal">
  <div class="modal-content">
    <div class="p-6">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
          <i class="fas fa-exclamation text-blue-500 text-xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800">Confirm Submission</h3>
      </div>
      <p class="text-gray-600 mb-6">Are you sure you're ready to submit this Individual Development Plan? Once submitted, you won't be able to make changes.</p>
      <div class="flex justify-end space-x-3">
        <button class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100" id="cancel-submit">Cancel</button>
        <button class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-accent" id="confirm-submit">Submit Now</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-backdrop" id="success-modal">
  <div class="modal-content">
    <div class="p-6">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4">
          <i class="fas fa-check text-green-500 text-xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800">Submission Successful!</h3>
      </div>
      <p class="text-gray-600 mb-6">Your Individual Development Plan has been successfully submitted. You can view it in your submitted forms section.</p>
      <div class="flex justify-end">
        <button class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-accent" id="close-success-modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize form data from localStorage if available
    let formData = JSON.parse(localStorage.getItem('idpFormData')) || {
      status: 'draft',
      personalInfo: {},
      purpose: {},
      longTermGoals: [],
      shortTermGoals: [],
      certification: {}
    };
    
    let submittedForms = JSON.parse(localStorage.getItem('submittedIdpForms')) || [];
    
    // Update UI with saved data
    updateFormStatus();
    loadSavedData();
    renderSubmittedForms();
    
    // Print functionality
    document.getElementById('print-btn')?.addEventListener('click', function() {
      window.print();
    });
    
    // Form field interactions
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('ring-2', 'ring-primary', 'ring-opacity-50');
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.classList.remove('ring-2', 'ring-primary', 'ring-opacity-50');
      });
      
      // Save on change
      input.addEventListener('change', saveFormData);
      input.addEventListener('input', saveFormData);
    });
    
    // Checkbox styling and saving
    const checkboxes = document.querySelectorAll('.checkbox-custom');
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        if (this.checked) {
          this.parentElement.classList.add('font-medium', 'text-primary');
        } else {
          this.parentElement.classList.remove('font-medium', 'text-primary');
        }
        saveFormData();
      });
    });
    
    // Table row hover effect
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
      row.addEventListener('mouseenter', function() {
        this.classList.add('bg-blue-50');
      });
      
      row.addEventListener('mouseleave', function() {
        this.classList.remove('bg-blue-50');
      });
    });
    
    // Add row to long term goals table
    document.getElementById('add-long-term-row')?.addEventListener('click', function() {
      const tbody = document.querySelector('#long-term-goals tbody');
      const newRow = document.createElement('tr');
      newRow.innerHTML = `
        <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Enter area"></td>
        <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Enter activity"></td>
        <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50"></td>
        <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" placeholder="Enter stage"></td>
      `;
      tbody.appendChild(newRow);
      
      // Add event listeners to new inputs
      const newInputs = newRow.querySelectorAll('input');
      newInputs.forEach(input => {
        input.addEventListener('change', saveFormData);
        input.addEventListener('input', saveFormData);
      });
      
      saveFormData();
    });
    
    // Save draft button
    document.getElementById('save-draft-btn')?.addEventListener('click', function() {
      saveFormData();
      Swal.fire({
        icon: 'success',
        title: 'Draft Saved',
        text: 'Your form has been saved as a draft.',
        showConfirmButton: false,
        timer: 1500
      });
    });
    
    // Submit form button
    document.getElementById('submit-form-btn')?.addEventListener('click', function() {
      document.getElementById('confirmation-modal').classList.add('active');
    });
    
    // Cancel submit
    document.getElementById('cancel-submit')?.addEventListener('click', function() {
      document.getElementById('confirmation-modal').classList.remove('active');
    });
    
    // Confirm submit
    document.getElementById('confirm-submit')?.addEventListener('click', function() {
      // Save final version
      saveFormData();
      
      // Mark as submitted
      formData.status = 'submitted';
      formData.submittedAt = new Date().toISOString();
      
      // Add to submitted forms
      submittedForms.unshift({
        ...formData,
        id: Date.now().toString()
      });
      
      // Save to localStorage
      localStorage.setItem('submittedIdpForms', JSON.stringify(submittedForms));
      
      // Clear draft
      localStorage.removeItem('idpFormData');
      formData = {
        status: 'draft',
        personalInfo: {},
        purpose: {},
        longTermGoals: [],
        shortTermGoals: [],
        certification: {}
      };
      
      // Update UI
      updateFormStatus();
      renderSubmittedForms();
      
      // Show success modal
      document.getElementById('confirmation-modal').classList.remove('active');
      document.getElementById('success-modal').classList.add('active');
    });
    
    // Close success modal
    document.getElementById('close-success-modal')?.addEventListener('click', function() {
      document.getElementById('success-modal').classList.remove('active');
    });
    
    // Toggle submitted forms section
    document.getElementById('submitted-forms-toggle')?.addEventListener('click', function() {
      const container = document.getElementById('submitted-forms-container');
      const arrow = document.getElementById('submitted-forms-arrow');
      
      container.classList.toggle('hidden');
      arrow.classList.toggle('transform');
      arrow.classList.toggle('rotate-180');
    });
    
    // View submitted form
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('view-submitted-btn')) {
        const formId = e.target.dataset.id;
        const form = submittedForms.find(f => f.id === formId);
        
        if (form) {
          // Fill the current form with the submitted data
          formData = JSON.parse(JSON.stringify(form));
          loadSavedData();
          updateFormStatus();
          
          // Scroll to top
          window.scrollTo(0, 0);
          
          Swal.fire({
            icon: 'info',
            title: 'Viewing Submitted Form',
            text: 'You are now viewing a submitted form. To create a new one, refresh the page.',
            confirmButtonText: 'OK'
          });
        }
      }
    });
    
    // Function to save form data
    function saveFormData() {
      // Personal Info
      formData.personalInfo = {
        name: document.getElementById('name').value,
        position: document.getElementById('position').value,
        salaryGrade: document.getElementById('salary-grade').value,
        yearsPosition: document.getElementById('years-position').value,
        yearsLspu: document.getElementById('years-lspu').value,
        yearsOther: document.getElementById('years-other').value,
        division: document.getElementById('division').value,
        office: document.getElementById('office').value,
        address: document.getElementById('address').value,
        supervisor: document.getElementById('supervisor').value
      };
      
      // Purpose
      formData.purpose = {
        purpose1: document.getElementById('purpose1').checked,
        purpose2: document.getElementById('purpose2').checked,
        purpose3: document.getElementById('purpose3').checked,
        purpose4: document.getElementById('purpose4').checked,
        purpose5: document.getElementById('purpose5').checked,
        purposeOther: document.getElementById('purpose-other').value
      };
      
      // Long Term Goals
      formData.longTermGoals = [];
      const longTermRows = document.querySelectorAll('#long-term-goals tbody tr');
      longTermRows.forEach(row => {
        const inputs = row.querySelectorAll('input');
        formData.longTermGoals.push({
          area: inputs[0].value,
          activity: inputs[1].value,
          date: inputs[2].value,
          stage: inputs[3].value
        });
      });
      
      // Short Term Goals
      formData.shortTermGoals = [];
      const shortTermRows = document.querySelectorAll('#short-term-goals tbody tr');
      shortTermRows.forEach(row => {
        const inputs = row.querySelectorAll('input');
        formData.shortTermGoals.push({
          area: inputs[0].value,
          priority: inputs[1].value,
          activity: inputs[2].value,
          date: inputs[3].value,
          responsible: inputs[4].value,
          stage: inputs[5].value
        });
      });
      
      // Certification
      formData.certification = {
        employeeName: document.getElementById('employee-name').value,
        employeeDate: document.getElementById('employee-date').value,
        supervisorName: document.getElementById('supervisor-name').value,
        supervisorDate: document.getElementById('supervisor-date').value,
        directorName: document.getElementById('director-name').value,
        directorDate: document.getElementById('director-date').value
      };
      
      // Save to localStorage
      localStorage.setItem('idpFormData', JSON.stringify(formData));
      
      // Update form status
      updateFormStatus();
    }
    
    // Function to load saved data
    function loadSavedData() {
      // Personal Info
      if (formData.personalInfo) {
        document.getElementById('name').value = formData.personalInfo.name || '';
        document.getElementById('position').value = formData.personalInfo.position || '';
        document.getElementById('salary-grade').value = formData.personalInfo.salaryGrade || '';
        document.getElementById('years-position').value = formData.personalInfo.yearsPosition || '';
        document.getElementById('years-lspu').value = formData.personalInfo.yearsLspu || '';
        document.getElementById('years-other').value = formData.personalInfo.yearsOther || '';
        document.getElementById('division').value = formData.personalInfo.division || '';
        document.getElementById('office').value = formData.personalInfo.office || '';
        document.getElementById('address').value = formData.personalInfo.address || '';
        document.getElementById('supervisor').value = formData.personalInfo.supervisor || '';
      }
      
      // Purpose
      if (formData.purpose) {
        document.getElementById('purpose1').checked = formData.purpose.purpose1 || false;
        document.getElementById('purpose2').checked = formData.purpose.purpose2 || false;
        document.getElementById('purpose3').checked = formData.purpose.purpose3 || false;
        document.getElementById('purpose4').checked = formData.purpose.purpose4 || false;
        document.getElementById('purpose5').checked = formData.purpose.purpose5 || false;
        document.getElementById('purpose-other').value = formData.purpose.purposeOther || '';
        
        // Update checkbox styling
        checkboxes.forEach(checkbox => {
          if (checkbox.checked) {
            checkbox.parentElement.classList.add('font-medium', 'text-primary');
          } else {
            checkbox.parentElement.classList.remove('font-medium', 'text-primary');
          }
        });
      }
      
      // Long Term Goals
      if (formData.longTermGoals && formData.longTermGoals.length > 0) {
        const tbody = document.querySelector('#long-term-goals tbody');
        tbody.innerHTML = ''; // Clear existing rows
        
        formData.longTermGoals.forEach(goal => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.area || ''}"></td>
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.activity || ''}"></td>
            <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.date || ''}"></td>
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.stage || ''}"></td>
          `;
          tbody.appendChild(row);
        });
      }
      
      // Short Term Goals
      if (formData.shortTermGoals && formData.shortTermGoals.length > 0) {
        const tbody = document.querySelector('#short-term-goals tbody');
        tbody.innerHTML = ''; // Clear existing rows
        
        formData.shortTermGoals.forEach(goal => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.area || ''}" readonly></td>
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.priority || ''}"></td>
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.activity || ''}"></td>
            <td><input type="date" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.date || ''}"></td>
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.responsible || ''}"></td>
            <td><input type="text" class="w-full border-none bg-transparent focus:bg-blue-50" value="${goal.stage || ''}"></td>
          `;
          tbody.appendChild(row);
        });
      }
      
      // Certification
      if (formData.certification) {
        document.getElementById('employee-name').value = formData.certification.employeeName || '';
        document.getElementById('employee-date').value = formData.certification.employeeDate || '';
        document.getElementById('supervisor-name').value = formData.certification.supervisorName || '';
        document.getElementById('supervisor-date').value = formData.certification.supervisorDate || '';
        document.getElementById('director-name').value = formData.certification.directorName || '';
        document.getElementById('director-date').value = formData.certification.directorDate || '';
      }
    }
    
    // Function to update form status display
    function updateFormStatus() {
      const statusElement = document.getElementById('form-status');
      if (formData.status === 'submitted') {
        statusElement.textContent = 'Submitted';
        statusElement.className = 'ml-2 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800';
      } else {
        statusElement.textContent = 'Draft';
        statusElement.className = 'ml-2 px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800';
      }
    }
    
    // Function to render submitted forms
    function renderSubmittedForms() {
      const container = document.getElementById('submitted-forms-list');
      const countElement = document.getElementById('submitted-count');
      
      countElement.textContent = submittedForms.length;
      
      if (submittedForms.length === 0) {
        document.getElementById('no-forms-message').classList.remove('hidden');
        return;
      } else {
        document.getElementById('no-forms-message').classList.add('hidden');
      }
      
      container.innerHTML = '';
      
      submittedForms.forEach(form => {
        const date = new Date(form.submittedAt);
        const formattedDate = date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
        
        const card = document.createElement('div');
        card.className = 'submitted-form-card bg-white p-4 hover:shadow-card cursor-pointer';
        card.innerHTML = `
          <div class="flex justify-between items-start">
            <div>
              <h4 class="font-medium text-gray-800">${form.personalInfo.name || 'Unnamed Form'}</h4>
              <p class="text-sm text-gray-500 mt-1">Submitted on ${formattedDate}</p>
            </div>
            <div class="flex space-x-2">
              <button class="view-submitted-btn px-3 py-1 bg-primary text-white text-sm rounded-lg hover:bg-accent" data-id="${form.id}">
                <i class="fas fa-eye mr-1"></i> View
              </button>
              <button class="print-submitted-btn px-3 py-1 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300" data-id="${form.id}">
                <i class="fas fa-print mr-1"></i> Print
              </button>
            </div>
          </div>
          <div class="mt-2 text-sm text-gray-600">
            <span class="inline-block bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs">${form.personalInfo.position || 'No position'}</span>
            <span class="inline-block bg-green-100 text-green-800 px-2 py-0.5 rounded-full text-xs ml-2">${form.personalInfo.division || 'No division'}</span>
          </div>
        `;
        
        container.appendChild(card);
      });
    }
  });
</script>
</body>
</html>