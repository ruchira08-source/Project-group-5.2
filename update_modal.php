<?php
/* Update add user modal */
?>
    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="roleSelect" onchange="handleRoleChange()" required>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Enter full name">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="Enter email address">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" required placeholder="Enter phone number">
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <select name="department" id="deptSelect" onchange="updatePRN()" required>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Computer Engineering">Computer Engineering</option>
                        <option value="Electronics & Telecommunication">Electronics & Telecommunication</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                    </select>
                </div>

                <!-- Student specific fields -->
                <div id="studentFields">
                    <div class="form-group" id="prnGroup">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">
                            Automatic Student PRN
                        </label>
                        <div style="display: flex; align-items: center; gap: 0.6rem; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 0.6rem 0.75rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="autoPrnToggle" checked onchange="toggleAutoPrnMode(this)" style="width: 18px; height: 18px; accent-color: #4f46e5; cursor: pointer;">
                            <label for="autoPrnToggle" style="font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; margin: 0; user-select: none;">Auto-generate PRN by Department</label>
                        </div>
                        <div>
                            <input type="text" name="prn" id="prnInput" readonly value="<?= htmlspecialchars(generate_next_prn($db, 'Information Technology')) ?>" style="background-color: #e0e7ff; font-weight: 700; color: #3730a3; border: 1.5px solid #6366f1; cursor: not-allowed; font-size: 1rem; letter-spacing: 0.5px; width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px;" placeholder="Auto-generated PRN">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" id="yearSelect">
                            <option value="First Year">First Year</option>
                            <option value="Second Year">Second Year</option>
                            <option value="Third Year">Third Year</option>
                            <option value="Final Year">Final Year</option>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" id="semesterSelect">
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="3rd Semester">3rd Semester</option>
                                <option value="4th Semester">4th Semester</option>
                                <option value="5th Semester">5th Semester</option>
                                <option value="6th Semester">6th Semester</option>
                                <option value="7th Semester">7th Semester</option>
                                <option value="8th Semester">8th Semester</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Division</label>
                            <select name="division" id="divisionSelect">
                                <option value="A">Div A</option>
                                <option value="B">Div B</option>
                                <option value="C">Div C</option>
                                <option value="D">Div D</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Faculty specific fields -->
                <div id="facultyFields" style="display: none;">
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation">
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Professor">Professor</option>
                            <option value="Professor & HOD">Professor & HOD</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subjects</label>
                        <input type="text" name="subjects" placeholder="e.g. Data Structures, OS">
                    </div>
                </div>

                <button type="submit" class="submit-btn">Save User</button>
            </form>
        </div>
    </div>
