import hashlib
import json

from app.help_desk_agent_workspace import (
    AgentWorkloadEvidence,
    AssignmentPlanRequest,
    QueueCaseEvidence,
    QueueEvaluationRequest,
    SavedViewEvidence,
    WorkspaceReportIntegrityEvidence,
    assess_saved_view,
    assess_workload,
    evaluate_queue,
    plan_assignment,
    verify_workspace_report,
)


def cases():
    return [
        QueueCaseEvidence(case_id=1, case_number="SC-2026-000001", status="open", priority="p2_high", assigned_user_id=7, updated_hours_ago=3),
        QueueCaseEvidence(case_id=2, case_number="SC-2026-000002", status="new", priority="normal", updated_hours_ago=1),
        QueueCaseEvidence(case_id=3, case_number="SC-2026-000003", status="escalated", priority="p1_critical", assigned_team="technical-review", updated_hours_ago=30),
        QueueCaseEvidence(case_id=4, case_number="SC-2026-000004", status="closed", priority="low", assigned_user_id=7, updated_hours_ago=200),
        QueueCaseEvidence(case_id=5, case_number="SC-2026-000005", status="resolved", priority="normal", assigned_user_id=8, updated_hours_ago=12, resolved_days_ago=2),
    ]


def test_my_open_queue():
    result = evaluate_queue(QueueEvaluationRequest(queue="my_open", current_user_id=7, cases=cases()))
    assert result.matched_case_ids == [1]
    assert result.public_workspace_api is False


def test_unassigned_queue():
    result = evaluate_queue(QueueEvaluationRequest(queue="unassigned", cases=cases()))
    assert result.matched_case_ids == [2]


def test_high_priority_queue():
    result = evaluate_queue(QueueEvaluationRequest(queue="high_priority", cases=cases()))
    assert result.matched_case_ids == [1, 3]


def test_recently_resolved_queue():
    result = evaluate_queue(QueueEvaluationRequest(queue="resolved_recent", resolved_days=14, cases=cases()))
    assert result.matched_case_ids == [5]


def test_assignment_plan_requires_target():
    result = plan_assignment(AssignmentPlanRequest(case_ids=[1, 2], operation="assign", actor_user_id=7))
    assert result.ready is False
    assert "assignment_target_required" in result.errors
    assert result.automatic_assignment is False


def test_assignment_plan_is_reviewable():
    result = plan_assignment(AssignmentPlanRequest(case_ids=[1, 2], operation="assign", actor_user_id=7, assigned_team="technical-review", reason="Defect review"))
    assert result.ready is True
    assert result.assignment_history_required is True
    assert result.human_confirmation_required is True


def test_workload_weighting():
    result = assess_workload(AgentWorkloadEvidence(agent_user_id=7, open_priorities=["p1_critical", "p2_high", "normal", "low"], warning_threshold=10, critical_threshold=20))
    assert result.weighted_load == 15
    assert result.state == "watch"
    assert result.automatic_reassignment is False


def test_saved_view_rejects_private_content():
    result = assess_saved_view(SavedViewEvidence(name="Escalations", owner_user_id=7, requester_user_id=7, query={"queue": "escalated"}, contains_private_message_body=True))
    assert result.accepted is False
    assert "private_message_body_must_not_be_stored_in_saved_view" in result.errors


def test_shared_view_requires_permission():
    result = assess_saved_view(SavedViewEvidence(name="Team queue", visibility="shared", owner_user_id=7, requester_user_id=7, query={"team": "technical-review"}))
    assert result.accepted is False
    assert "shared_view_permission_required" in result.errors


def test_workspace_report_integrity():
    payload = {"queue": "unassigned", "count": 8}
    canonical = json.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    checksum = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
    assert verify_workspace_report(WorkspaceReportIntegrityEvidence(payload=payload, checksum=checksum)).valid is True
