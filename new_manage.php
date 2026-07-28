<?php
/*
This will be injected into admin_dashboard.php replacing lines 1296 to 1393.
*/
?>
            <div class="section-header">
                <h2>User Management</h2>
                <div style="display: flex; gap: 10px;">
                    <button class="add-btn" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add User</button>
                    <button class="add-btn" onclick="openImportModal()" style="background-color: #10b981;"><i class="fa-solid fa-file-import"></i> Import CSV</button>
                </div>
            </div>

            <style>
                .um-tabs { display: flex; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
                .um-tab { padding: 10px 20px; cursor: pointer; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; transition: all 0.2s; }
                .um-tab:hover { color: #4f46e5; }
                .um-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; }
                .um-panel { display: none; }
                .um-panel.active { display: block; }
            </style>

            <div class="um-tabs">
                <div class="um-tab active" onclick="switchUmTab('students', this)">Students</div>
                <div class="um-tab" onclick="switchUmTab('faculty', this)">Faculty</div>
                <div class="um-tab" onclick="switchUmTab('hod', this)">HOD</div>
                <div class="um-tab" onclick="switchUmTab('admin', this)">Admin</div>
            </div>

            <script>
                function switchUmTab(tabId, el) {
                    document.querySelectorAll('.um-panel').forEach(p => p.classList.remove('active'));
                    document.querySelectorAll('.um-tab').forEach(t => t.classList.remove('active'));
                    document.getElementById('um-' + tabId).classList.add('active');
                    el.classList.add('active');
                }
            </script>

            <div class="table-container um-panel active" id="um-students">
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>PRN (Username)</th>
                            <th>Password</th>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Division</th>
                            <th>Roll No</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['students'] as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($s['prn'] ?? $s['id'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($s['password'] ?? $s['prn'] ?? ''); ?></span></td>
                            <td><?php echo htmlspecialchars($s['department'] ?? 'Information Technology'); ?></td>
                            <td><?php echo htmlspecialchars($s['year'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['semester'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['division'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['roll_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['phone'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container um-panel" id="um-faculty">
                <table>
                    <thead>
                        <tr>
                            <th>Faculty Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Department</th>
                            <th>Assigned Subject</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['faculty'] as $f): if(strpos(strtolower($f['designation'] ?? ''), 'hod') !== false) continue; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($f['username'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($f['password'] ?? $f['username'] ?? ''); ?></span></td>
                            <td><?php echo htmlspecialchars($f['department'] ?? 'Information Technology'); ?></td>
                            <td><?php echo htmlspecialchars($f['subjects'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($f['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($f['phone'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container um-panel" id="um-hod">
                <table>
                    <thead>
                        <tr>
                            <th>HOD Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Department</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['faculty'] as $f): if(strpos(strtolower($f['designation'] ?? ''), 'hod') === false) continue; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($f['username'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($f['password'] ?? $f['username'] ?? ''); ?></span></td>
                            <td><?php echo htmlspecialchars($f['department'] ?? 'Information Technology'); ?></td>
                            <td><?php echo htmlspecialchars($f['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($f['phone'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container um-panel" id="um-admin">
                <table>
                    <thead>
                        <tr>
                            <th>Admin Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['users'] as $u): if(($u['role'] ?? '') !== 'admin') continue; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($u['username'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($u['password'] ?? '********'); ?></span></td>
                            <td><span class='badge badge-admin'>Admin</span></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
