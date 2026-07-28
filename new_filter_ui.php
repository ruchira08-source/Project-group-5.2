                    <!-- Filter UI -->
                    <div style="background:white; border:1px solid var(--border-color); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; box-shadow:var(--box-shadow-subtle);">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:1rem; align-items:end;">
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Department</label>
                                <select id="filter_dept" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <option value="Computer Engineering">Computer Engineering</option>
                                    <option value="Information Technology">Information Technology</option>
                                    <option value="Electronics Engineering">Electronics</option>
                                    <option value="Mechanical Engineering">Mechanical</option>
                                    <option value="Civil Engineering">Civil</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Year</label>
                                <select id="filter_year" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <option value="First Year">First Year</option>
                                    <option value="Second Year">Second Year</option>
                                    <option value="Third Year">Third Year</option>
                                    <option value="Final Year">Final Year</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Semester</label>
                                <select id="filter_sem" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <?php 
                                    $sem_arr = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
                                    foreach($sem_arr as $s) echo "<option value='{$s} Semester'>{$s} Semester</option>"; 
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Division</label>
                                <select id="filter_div" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <option value="A">Div A</option>
                                    <option value="B">Div B</option>
                                    <option value="C">Div C</option>
                                    <option value="D">Div D</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Subject</label>
                                <select id="filter_subject" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <?php foreach($faculty_subjects as $sub) {
                                        if (trim($sub) !== '') echo "<option value='" . htmlspecialchars($sub) . "'>" . htmlspecialchars($sub) . "</option>";
                                    } ?>
                                </select>
                            </div>
                            
                            <div id="filter_unit_wrapper" style="display:none;">
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Unit</label>
                                <select id="filter_unit" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select Unit --</option>
                                </select>
                            </div>

                            <div id="filter_assignment_wrapper" style="display:none; grid-column: span 2;">
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Select Assignment</label>
                                <select id="filter_assignment" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;" onchange="updateCascadingFilters()">
                                    <option value="">-- Select an Assignment --</option>
                                </select>
                            </div>

                            <div id="filter_btn_wrapper" style="display:none;">
                                <button onclick="loadAssignmentStudents()" style="width:100%; background:#4f46e5; color:white; border:none; padding:0.65rem; border-radius:6px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:0.4rem; font-size:0.9rem;">
                                    <i class="fa-solid fa-search"></i> View Students
                                </button>
                            </div>
                        </div>
                    </div>
