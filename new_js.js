        function loadAssignmentStudents() {
            var f_dept = document.getElementById('filter_dept').value;
            var f_sem = document.getElementById('filter_sem').value;
            var f_div = document.getElementById('filter_div').value;
            var f_assign = document.getElementById('filter_assignment').value;

            if (!f_assign) {
                alert('Please select an Assignment first.');
                return;
            }
            
            var tbody = document.getElementById('grading_tbody');
            tbody.innerHTML = '';
            
            var subs = _allSubmissions[f_assign] || {};
            var total = 0;
            var submitted = 0;

            _allStudents.forEach(function(stu) {
                var m_dept = (!f_dept || (stu.department && (stu.department.indexOf(f_dept) !== -1 || f_dept.indexOf(stu.department) !== -1)));
                var m_sem = (!f_sem || stu.semester === f_sem);
                var m_div = (!f_div || stu.division === f_div);

                if (m_dept && m_sem && m_div) {
                    total++;
                    var sub = subs[stu.id];
                    var hasSub = !!sub;
                    if (hasSub) submitted++;

                    var tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #e2e8f0';
                    
                    var statusHtml = hasSub 
                        ? '<span style="background:#dcfce7; color:#15803d; padding:0.25rem 0.65rem; border-radius:20px; font-weight:700; font-size:0.75rem;"><i class="fa-solid fa-check"></i> ' + (sub.status || 'Submitted') + '</span>'
                        : '<span style="background:#fee2e2; color:#b91c1c; padding:0.25rem 0.65rem; border-radius:20px; font-weight:700; font-size:0.75rem;"><i class="fa-solid fa-xmark"></i> Pending</span>';
                    
                    var filePath = hasSub ? (sub.file_path || sub.file) : '';
                    var fileExt = filePath ? filePath.split('.').pop() : 'pdf';
                    var fileHtml = hasSub
                        ? `<div style="display:flex; gap:0.5rem; flex-direction:column;">
                               <div style="display:flex; gap:0.5rem; align-items:center;">
                                   <a href="uploads/${filePath}" target="_blank" style="display:inline-flex; align-items:center; gap:0.35rem; color:#0284c7; font-weight:600; text-decoration:none; font-size:0.85rem;" download><i class="fa-solid fa-download"></i> Download</a>
                                   <button type="button" onclick="toggleFacultyPreview('uploads/${filePath}','${fileExt}','mgmt_prev_${sub.id}')" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:4px; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:4px;"><i class="fa-solid fa-eye"></i> Preview</button>
                               </div>
                               <div id="mgmt_prev_${sub.id}" style="display:none; border:1px solid #cbd5e1; border-radius:6px; padding:0.25rem; background:#fafafa; margin-top:0.5rem; width:250px;"></div>
                           </div>`
                        : '<span style="color:#94a3b8; font-size:0.85rem;">No File</span>';
                    
                    var marksHtml = hasSub ? (sub.marks || '') : '-';
                    var subId = hasSub ? sub.id : '';
                    
                    var actionHtml = '';
                    if (hasSub) {
                        actionHtml = `
                            <form method="POST" action="faculty_dashboard.php" style="margin:0; display:flex; align-items:center; gap:0.5rem; justify-content:center;">
                                <input type="hidden" name="action" value="grade_assignment">
                                <input type="hidden" name="assignment_id" value="${subId}">
                                <input type="text" name="marks" value="${sub.marks || ''}" placeholder="Marks" required style="width:70px; padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px; text-align:center; font-size:0.85rem;">
                                <select name="status" style="padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px; font-size:0.85rem; width:100px;">
                                    <option value="Graded" ${sub.status === 'Graded' ? 'selected' : ''}>Graded</option>
                                    <option value="Returned for Resubmission" ${sub.status === 'Returned for Resubmission' ? 'selected' : ''}>Resubmit</option>
                                </select>
                                <button type="submit" style="padding:0.4rem 0.75rem; background:#3b82f6; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600; font-size:0.8rem;">Save</button>
                            </form>
                        `;
                    } else {
                        actionHtml = '<span style="color:#94a3b8; font-size:0.85rem;">-</span>';
                    }

                    tr.innerHTML = `
                        <td style="padding:0.85rem 1.25rem; font-size:0.9rem; color:#475569; font-family:monospace;">${stu.id}</td>
                        <td style="padding:0.85rem 1.25rem; font-size:0.9rem; font-weight:600; color:#1e293b;">${stu.name}</td>
                        <td style="padding:0.85rem 1.25rem; text-align:center;">${statusHtml}</td>
                        <td style="padding:0.85rem 1.25rem;">${fileHtml}</td>
                        <td style="padding:0.85rem 1.25rem; font-weight:700;">${marksHtml}</td>
                        <td style="padding:0.85rem 1.25rem; text-align:center;">${actionHtml}</td>
                    `;
                    tbody.appendChild(tr);
                }
            });

            if (total === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="padding:2rem; text-align:center; color:#94a3b8;">No students match the selected filters.</td></tr>';
            }
            
            var pending = total - submitted;
            document.getElementById('grading_stats').innerText = 'Total: ' + total + ' | Submitted: ' + submitted + ' | Pending: ' + pending;
            document.getElementById('grading_container').style.display = 'block';
        }
