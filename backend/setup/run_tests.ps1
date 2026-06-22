$b = "http://localhost:8080/api"
$ts = Get-Date -Format "HHmmssff"
$em = "t$ts@tf.test"
$p = 0; $f = 0

function T($n, $ok, $d = "") {
  if ($ok) { Write-Host "  PASS  $n $d" -ForegroundColor Green; $script:p++ }
  else     { Write-Host "  FAIL  $n $d" -ForegroundColor Red;   $script:f++ }
}

function Req($method, $url, $body = $null, $tok = "") {
  $hd = @{ "Content-Type" = "application/json" }
  if ($tok -ne "") { $hd["Authorization"] = "Bearer $tok" }
  $opts = @{ Uri = $url; Method = $method; Headers = $hd; UseBasicParsing = $true }
  if ($body) { $opts["Body"] = $body }
  return (Invoke-WebRequest @opts).Content | ConvertFrom-Json
}

Write-Host "`n TaskFlow Full Test Suite" -ForegroundColor Cyan
Write-Host ("-" * 50)

# 1 Health
$r = Req "GET" "$b/health"
T "Health check" ($r.data.status -eq "ok")

# 2 Register
$r = Req "POST" "$b/auth/register" "{`"name`":`"Test User`",`"email`":`"$em`",`"password`":`"Test@1234`"}"
$tok = $r.data.token
T "Register" ($r.success) "user=$em"

# 3 Login
$r = Req "POST" "$b/auth/login" "{`"email`":`"$em`",`"password`":`"Test@1234`"}"
T "Login" ($r.success)

# 4 Wrong password
try { Req "POST" "$b/auth/login" "{`"email`":`"$em`",`"password`":`"wrong`"}" | Out-Null; T "Wrong password rejected" $false }
catch { T "Wrong password rejected" $true }

# 5 /auth/me
$r = Req "GET" "$b/auth/me" $null $tok
T "/auth/me returns user" ($r.data.user.name -eq "Test User")

# 6 Bad token
try { Req "GET" "$b/projects" $null "BADTOKEN" | Out-Null; T "Bad token rejected" $false }
catch { T "Bad token rejected" $true }

# 7 Create project
$r = Req "POST" "$b/projects" '{"name":"E2E Project","color":"#6366f1"}' $tok
$pid = $r.data.project.id
T "Create project" ($pid -gt 0) "id=$pid"

# 8 List projects
$r = Req "GET" "$b/projects" $null $tok
T "List projects" ($r.data.projects.Count -ge 1)

# 9 Update project
$r = Req "PUT" "$b/projects/$pid" '{"name":"Updated Project","color":"#8b5cf6"}' $tok
T "Update project" ($r.success)

# 10 Create task
$r = Req "POST" "$b/projects/$pid/tasks" '{"title":"E2E Task","priority":"high","status":"todo","description":"Test","due_date":"2026-09-01"}' $tok
$tid = $r.data.task.id
T "Create task" ($tid -gt 0) "id=$tid"

# 11 Get task
$r = Req "GET" "$b/tasks/$tid" $null $tok
T "Get task by id" ($r.data.task.title -eq "E2E Task")

# 12 Update task
$r = Req "PUT" "$b/tasks/$tid" '{"title":"Updated Task","description":"changed","status":"in_progress","priority":"urgent"}' $tok
T "Update task" ($r.success)

# 13 Status patch
$r = Req "PATCH" "$b/tasks/$tid/status" '{"status":"review"}' $tok
T "Status → review" ($r.success)

# 14 Stats
$r = Req "GET" "$b/projects/$pid/tasks" $null $tok
T "Stats total=1" ($r.data.stats.total -eq 1) "total=$($r.data.stats.total)"

# 15 Search
$r = Req "GET" "$b/projects/$pid/tasks?search=Updated" $null $tok
T "Search filter" ($r.data.tasks.Count -ge 1)

# 16 Priority filter
$r = Req "GET" "$b/projects/$pid/tasks?priority=urgent" $null $tok
T "Priority filter" ($r.data.tasks.Count -ge 1)

# 17 Status filter
$r = Req "GET" "$b/projects/$pid/tasks?status=review" $null $tok
T "Status filter" ($r.data.tasks.Count -ge 1)

# 18 Soft delete
$r = Req "DELETE" "$b/tasks/$tid" $null $tok
T "Soft delete task" ($r.success)

# 19 Hidden after delete
$r = Req "GET" "$b/projects/$pid/tasks" $null $tok
T "Hidden after delete" ($r.data.stats.total -eq 0)

# 20 Restore
$r = Req "PATCH" "$b/tasks/$tid/restore" '{}' $tok
T "Restore task" ($r.success)

# 21 Visible after restore
$r = Req "GET" "$b/projects/$pid/tasks" $null $tok
T "Visible after restore" ($r.data.stats.total -eq 1)

# 22 Activity log
$r = Req "GET" "$b/projects/$pid/activity" $null $tok
T "Project activity log" ($r.data.logs.Count -ge 1) "$($r.data.logs.Count) logs"

# 23 My activity
$r = Req "GET" "$b/activity/me" $null $tok
T "My activity" ($r.data.logs.Count -ge 1)

# 24 Frontend HTML
$fe = Invoke-WebRequest "http://localhost:8080/" -UseBasicParsing
T "Frontend HTML loads" ($fe.StatusCode -eq 200 -and $fe.Content -match "TaskFlow")
T "confirm-modal hidden on load" ($fe.Content -match 'id="confirm-modal"[^>]*style="display:none"')
T "task-modal hidden on load"    ($fe.Content -match 'id="task-modal"[^>]*style="display:none"')
T "project-modal hidden on load" ($fe.Content -match 'id="project-modal"[^>]*style="display:none"')

# JS checks
$js = Invoke-WebRequest "http://localhost:8080/src/js/app.js" -UseBasicParsing
T "JS file serves" ($js.StatusCode -eq 200 -and $js.Content.Length -gt 5000) "$($js.Content.Length) bytes"
T "No duplicate confirmDelete" (($js.Content.Split("function confirmDelete").Count - 1) -eq 1)
T "No duplicate pendingDeleteId" (($js.Content.Split("let pendingDeleteId").Count - 1) -eq 1)
T "openModal helper present"   ($js.Content -match "function openModal")
T "closeModal helper present"  ($js.Content -match "function closeModal")

# CSS checks
$css = Invoke-WebRequest "http://localhost:8080/src/css/styles.css" -UseBasicParsing
T "CSS serves correctly" ($css.StatusCode -eq 200) "$($css.Content.Length) bytes"
T "CSS modal display:none default" ($css.Content -match "display:none")
T "CSS is-open animation class"    ($css.Content -match "is-open")

Write-Host ("-" * 50)
$total = $p + $f
Write-Host " Results: $p/$total passed" -ForegroundColor $(if ($f -eq 0) { "Green" } else { "Yellow" })
if ($f -gt 0) { Write-Host " $f test(s) failed" -ForegroundColor Red }
else          { Write-Host " All tests passed!" -ForegroundColor Green }
