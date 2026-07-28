        function updateCascadingFilters() {
            var f_dept = document.getElementById('filter_dept').value;
            var f_year = document.getElementById('filter_year').value;
            var f_sem = document.getElementById('filter_sem').value;
            var f_div = document.getElementById('filter_div').value;
            var f_subj = document.getElementById('filter_subject').value;
            
            var unitWrapper = document.getElementById('filter_unit_wrapper');
            var unitSel = document.getElementById('filter_unit');
            
            var assignWrapper = document.getElementById('filter_assignment_wrapper');
            var assignSel = document.getElementById('filter_assignment');
            
            var btnWrapper = document.getElementById('filter_btn_wrapper');

            if (f_dept && f_year && f_sem && f_div && f_subj) {
                unitWrapper.style.display = 'block';
                
                var availableUnits = new Set();
                var filteredSas = _myAssignments.filter(function(a) {
                    var matchDept = (a.department.indexOf(f_dept) !== -1 || f_dept.indexOf(a.department) !== -1);
                    return matchDept &&
                           a.semester === f_sem &&
                           a.division === f_div &&
                           a.subject_name === f_subj;
                });
                
                filteredSas.forEach(function(a) {
                    if (a.unit) availableUnits.add(a.unit);
                });
                
                var currentUnit = unitSel.value;
                unitSel.innerHTML = '<option value="">-- Select Unit --</option>';
                var unitsArray = Array.from(availableUnits).sort(function(a,b){return a-b;});
                unitsArray.forEach(function(u) {
                    unitSel.innerHTML += '<option value="' + u + '">Unit ' + u + '</option>';
                });
                
                if (availableUnits.has(parseInt(currentUnit))) {
                    unitSel.value = currentUnit;
                }
            } else {
                unitWrapper.style.display = 'none';
                unitSel.value = '';
            }

            var f_unit = unitSel.value;
            if (f_unit) {
                assignWrapper.style.display = 'block';
                
                var filteredAssigns = _myAssignments.filter(function(a) {
                    var matchDept = (a.department.indexOf(f_dept) !== -1 || f_dept.indexOf(a.department) !== -1);
                    return matchDept &&
                           a.semester === f_sem &&
                           a.division === f_div &&
                           a.subject_name === f_subj &&
                           a.unit == f_unit;
                });
                
                var currentAssign = assignSel.value;
                assignSel.innerHTML = '<option value="">-- Select an Assignment --</option>';
                var hasCurrent = false;
                filteredAssigns.forEach(function(a) {
                    assignSel.innerHTML += '<option value="' + a.id + '">' + a.subject_name + ' - ' + a.assignment_title + '</option>';
                    if (a.id == currentAssign) hasCurrent = true;
                });
                
                if (hasCurrent) assignSel.value = currentAssign;
            } else {
                assignWrapper.style.display = 'none';
                assignSel.value = '';
            }

            if (assignSel.value) {
                btnWrapper.style.display = 'block';
            } else {
                btnWrapper.style.display = 'none';
                var gradingCont = document.getElementById('grading_container');
                if (gradingCont) gradingCont.style.display = 'none';
                var gradingBody = document.getElementById('grading_tbody');
                if (gradingBody) gradingBody.innerHTML = '';
            }
        }
