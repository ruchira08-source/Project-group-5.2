                    <?php
                    $my_published_sas = [];
                    if (isset($db['subject_assignments'])) {
                        foreach ($db['subject_assignments'] as $sa) {
                            if (in_array($sa['subject_name'], $faculty_subjects)) {
                                // Fetch the unit from assignments
                                $unit = 1; // Default
                                if (isset($db['assignments'])) {
                                    foreach ($db['assignments'] as $a) {
                                        if ($a['id'] == $sa['assignment_id']) {
                                            $unit = $a['unit'];
                                            break;
                                        }
                                    }
                                }
                                $sa['unit'] = $unit;
                                $my_published_sas[] = $sa;
                            }
                        }
                    }
                    $my_published_sas = array_reverse($my_published_sas);
                    
                    // Output assignments array to JS for cascading filters
                    echo "<script>var _myAssignments = " . json_encode($my_published_sas) . ";</script>";
                    ?>
