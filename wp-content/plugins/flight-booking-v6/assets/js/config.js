/**
 * config.js - Stores API endpoint URLs
 * Centralized file to avoid hardcoding URLs everywhere
 */

const API_BASE_URL = "assets/fetch/";

const API_ENDPOINTS = {
    fetchAircraft: `${API_BASE_URL}fetch_aircraft.php`,
    fetchSimulators: `${API_BASE_URL}fetch_simulators.php`,
    fetchCFIs: `${API_BASE_URL}fetch_cfis.php`,
    fetchLocations: `${API_BASE_URL}fetch_locations.php`,
    fetchStudentLocation: `${API_BASE_URL}fetch_student_location.php`,
    fetchAssignedCFI: `${API_BASE_URL}fetch_assigned_cfi.php`,
    verifyStudent: `${API_BASE_URL}verify_student.php`,
    submitBooking: `${API_BASE_URL}submit_booking.php`
};

