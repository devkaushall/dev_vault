# Phase 6 Data Integrity

Runtime snapshots verified unauthorized calls do not mutate profiles. Assignments modify only the intended `rep_agent_id`/`rep_agency_id` metadata after validating the Agent-to-Agency relationship. A mismatched Agent/Agency assignment is rejected and the existing Property relationship remains unchanged.

Agency deletion is blocked while either Agents or Properties reference it. The authenticated Property relationship-removal operation clears only the two profile-reference fields and permits deletion after all references are removed. Permanent profile deletion removes matching relationship metadata without changing unrelated content.
