        function updateCascadingFilters() {
            var f_dept = document.getElementById('filter_dept').value;
            var f_year = document.getElementById('filter_year').value;
            var f_sem = document.getElementById('filter_sem').value;
            var f_div = document.getElementById('filter_div').value;
            var f_subj = document.getElementById('filter_subject').value;
            
            var cardsCont = document.getElementById('unit_cards_container');
            var gradingCont = document.getElementById('grading_container');

            // Hide grading initially
            if (gradingCont) gradingCont.style.display = 'none';

            if (f_dept && f_year && f_sem && f_div && f_subj) {
                cardsCont.style.display = 'grid';
                cardsCont.innerHTML = '';
                
                var units = [1, 2, 3, 4, 5, 6];
                
                units.forEach(function(u) {
                    var filteredAssigns = _myAssignments.filter(function(a) {
                        var matchDept = (a.department.indexOf(f_dept) !== -1 || f_dept.indexOf(a.department) !== -1);
                        var matchSubj = (a.subject_name.trim().toLowerCase() === f_subj.trim().toLowerCase());
                        return matchDept &&
                               a.semester === f_sem &&
                               a.division === f_div &&
                               matchSubj &&
                               a.unit == u;
                    });
                    
                    var html = '<div style="background:white; border:1px solid var(--border-color); border-radius:12px; padding:1.25rem; box-shadow:var(--box-shadow-subtle);">';
                    html += '<h4 style="margin:0 0 1rem 0; font-size:1.1rem; color:#4f46e5; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><i class="fa-solid fa-layer-group" style="margin-right:0.4rem;"></i>Unit ' + u + '</h4>';
                    
                    if (filteredAssigns.length > 0) {
                        html += '<ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.6rem;">';
                        filteredAssigns.forEach(function(a) {
                            var escapedTitle = a.assignment_title.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            html += '<li><button type="button" onclick="loadAssignmentStudents(' + a.id + ')" style="width:100%; text-align:left; background:#f8fafc; border:1px solid #e2e8f0; padding:0.75rem; border-radius:8px; cursor:pointer; color:#334155; font-weight:600; font-family:inherit; font-size:0.95rem; transition:all 0.2s;" onmouseover="this.style.background=\'#eff6ff\'; this.style.borderColor=\'#bfdbfe\'; this.style.color=\'#1e40af\';" onmouseout="this.style.background=\'#f8fafc\'; this.style.borderColor=\'#e2e8f0\'; this.style.color=\'#334155\';"><i class="fa-solid fa-file-alt" style="color:#64748b; margin-right:0.5rem;"></i>' + escapedTitle + '</button></li>';
                        });
                        html += '</ul>';
                    } else {
                        html += '<div style="font-size:0.85rem; color:#94a3b8; text-align:center; padding:1rem 0;">No assignments</div>';
                    }
                    
                    html += '</div>';
                    cardsCont.innerHTML += html;
                });
                
            } else {
                cardsCont.style.display = 'none';
                cardsCont.innerHTML = '';
            }
        }
