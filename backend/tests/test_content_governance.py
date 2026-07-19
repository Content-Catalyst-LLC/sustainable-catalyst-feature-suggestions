from datetime import datetime, timedelta, timezone

from app.content_governance import (
    ContentGovernanceBulkRequest,
    ContentGovernanceEvidence,
    ContentGovernanceQueueEvidence,
    evaluate_content_governance,
    plan_content_governance_bulk_action,
    summarize_content_governance_queue,
)


def iso_date(days: int) -> str:
    return (datetime.now(timezone.utc).date() + timedelta(days=days)).isoformat()


def test_verified_record_is_verified():
    result = evaluate_content_governance(
        ContentGovernanceEvidence(
            record_id=7,
            owner_assigned=True,
            technical_owner_assigned=True,
            require_technical_owner=True,
            integrity_state="publication_ready",
            integrity_score=96,
            verification_state="verified",
            last_verified_at=iso_date(-10),
            next_review_at=iso_date(120),
            workflow_state="published",
        )
    )
    assert result.queue_state == "verified"
    assert result.governance_score == 100
    assert result.blockers == []
    assert result.automatic_publication is False


def test_missing_owner_is_unassigned():
    result = evaluate_content_governance(
        ContentGovernanceEvidence(
            record_id=8,
            owner_assigned=False,
            integrity_state="publication_ready",
            integrity_score=90,
            next_review_at=iso_date(90),
        )
    )
    assert result.queue_state == "unassigned"
    assert "content_owner_required" in result.blockers
    assert "assign_content_owner" in result.required_actions


def test_integrity_failure_blocks_publication():
    result = evaluate_content_governance(
        ContentGovernanceEvidence(
            record_id=9,
            owner_assigned=True,
            integrity_state="publication_blocked",
            integrity_score=45,
            next_review_at=iso_date(90),
        )
    )
    assert result.queue_state == "publication_blocked"
    assert "integrity_threshold_not_met" in result.blockers


def test_overdue_review_is_prioritized():
    result = evaluate_content_governance(
        ContentGovernanceEvidence(
            record_id=10,
            owner_assigned=True,
            integrity_state="publication_ready",
            integrity_score=90,
            verification_state="verified",
            last_verified_at=iso_date(-200),
            next_review_at=iso_date(-2),
        )
    )
    assert result.queue_state == "overdue"
    assert result.days_until_review is not None
    assert result.days_until_review < 0


def test_superseded_record_is_not_in_active_queue():
    result = evaluate_content_governance(
        ContentGovernanceEvidence(
            record_id=11,
            superseded_by_id=12,
            verification_state="superseded",
        )
    )
    assert result.queue_state == "superseded"
    assert result.governance_score == 100


def test_queue_summary_normalizes_counts():
    summary = summarize_content_governance_queue(
        ContentGovernanceQueueEvidence(
            state_counts={"overdue": 3, "verified": 8, "publication_blocked": 2},
            priority_counts={"high": 4, "normal": 9},
            post_type_counts={"sc_support_article": 11, "sc_known_issue": 2},
        )
    )
    assert summary.total == 13
    assert summary.overdue == 3
    assert summary.verified == 8
    assert summary.publication_blocked == 2
    assert summary.human_review_required is True


def test_bulk_verification_requires_human_authority_and_note():
    plan = plan_content_governance_bulk_action(
        ContentGovernanceBulkRequest(
            record_ids=[1, 1, 2],
            action="verify",
            actor_can_verify=False,
            note="",
        )
    )
    assert plan.unique_records == 2
    assert plan.allowed is False
    assert "verification_capability_required" in plan.blockers
    assert "verification_note_required" in plan.blockers
    assert plan.automatic_publication is False


def test_bulk_assignment_requires_value():
    plan = plan_content_governance_bulk_action(
        ContentGovernanceBulkRequest(record_ids=[4], action="assign_owner", value="")
    )
    assert plan.allowed is False
    assert "bulk_value_required" in plan.blockers
