Form D-log input
1. Establish database migration
2. Establish interface (only input form atm)
3. fill in database migration example

Input form
Variables
public string $device_code // check
public string $machine_code // check

public array $logs = [['time' => '', 'temp' => '']]

rules()
device_code rquireed, exists in ins_stc_device, code
machine_code required, exists in ins_stc_machine, code
logs.*.time ['required', 'datetime']
logs.*.temp ['required', float?, max?]

with()
none

save()
validate
