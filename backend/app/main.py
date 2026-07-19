import json, os, re, hashlib
from datetime import datetime, timezone
from typing import List, Optional, Literal
from urllib import request, error
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, Field
from .survey_intelligence import SurveyAnalysisRequest, SurveyAnalysisResult, analyze_survey
from .guided_resolution import ResolutionRankRequest, ResolutionRankResult, rank_resolution
from .documentation_intelligence import DocumentationGapEvidence, DocumentationGapScore, SupportDemandEvidence, SupportDemandScore, score_documentation_gap, score_support_demand
from .product_support_platform import ProductSupportEvidence, ProductSupportOverview, ReleaseReadinessEvidence, ReleaseReadinessScore, summarize_product_support, score_release_readiness
from .support_content_operations import ProductOnboardingEvidence, ProductSupportReadiness, SourceDocument, ImportPlan, ImportSourceInspection, ImportBatchEvidence, ImportRecoveryPlan, ExportIntegrityEvidence, ExportIntegrityResult, score_product_readiness, plan_source_import, inspect_source_document, plan_import_recovery, verify_export_integrity
from .editorial_governance import EditorialTransitionEvidence, EditorialTransitionDecision, DocumentationStandardsEvidence, DocumentationStandardsScore, EditorialQueueEvidence, EditorialGovernanceSummary, evaluate_editorial_transition, score_documentation_standards, summarize_editorial_queue
from .repository_release_sync import RepositoryCandidateEvidence, RepositorySyncDecision, RepositoryDriftEvidence, RepositoryDriftResult, ReleaseSourceEvidence, ReleaseSyncPlan, LinkHealthEvidence, LinkHealthSummary, evaluate_repository_candidate, evaluate_repository_drift, plan_release_sync, summarize_link_health
from .support_reliability import ProductReliabilityEvidence, ProductReliabilityScore, ReliabilityTrendEvidence, ReliabilityTrendSummary, UnresolvedClusterEvidence, UnresolvedClusterPriority, ReliabilityReportIntegrityEvidence, ReliabilityReportIntegrityResult, score_product_reliability, summarize_reliability_trend, prioritize_unresolved_cluster, verify_reliability_report
from .cross_product_orchestration import IncidentImpactEvidence, IncidentImpactResult, ProductRouteEvidence, ProductRouteResult, ResolutionJourneyEvidence, ResolutionJourneyResult, OrchestrationReportEvidence, OrchestrationReportResult, evaluate_incident_impact, recommend_product_routes, build_resolution_journey, verify_orchestration_report
from .connected_support_operations import ConnectedOperationsEvidence, ConnectedOperationsScore, OperationsActionEvidence, OperationsActionPlan, OperationsReportEvidence, OperationsReportResult, score_connected_operations, plan_connected_action, verify_connected_operations_report
from .support_article_integrity import SupportArticleIntegrityEvidence, SupportArticleIntegrityResult, assess_support_article_integrity
from .support_discovery import DiscoverySearchRequest, DiscoverySearchResult, search_support_articles
from .unified_support_search import UnifiedSupportSearchRequest, UnifiedSupportSearchResult, search_unified_support

VERSION='5.3.0'
ANALYSIS_VERSION='5.1.0-1'
app=FastAPI(title='Sustainable Catalyst Product Support and Feedback Intelligence',version=VERSION)

class Submission(BaseModel):
    submission_id:str
    wordpress_id:Optional[int]=None
    title:str=Field(min_length=1,max_length=300)
    category:str='Other'
    priority:str='Medium'
    problem:str=''
    suggestion:str=''
    success_criteria:str=''
    beneficiaries:str=''
    implementation_notes:str=''
    product:List[str]=Field(default_factory=list)
    product_version:List[str]=Field(default_factory=list)
    component:List[str]=Field(default_factory=list)
    issue_type:List[str]=Field(default_factory=list)
    release:List[str]=Field(default_factory=list)
    source:str='wordpress'

class Scores(BaseModel):
    urgency:int=Field(ge=1,le=5)
    impact:int=Field(ge=1,le=5)
    effort:int=Field(ge=1,le=5)
    strategic_alignment:float=Field(ge=0,le=1)

class Analysis(BaseModel):
    submission_id:str
    analysis_id:str
    analysis_version:str=ANALYSIS_VERSION
    provider:str
    model:str
    generated_at:str
    summary:str
    feature_type:str
    platform_area:str
    topics:List[str]
    sentiment:Literal['positive','neutral','mixed','negative']
    safety_flags:List[str]
    duplicate_keys:List[str]
    suggested_roadmap_destination:str
    suggested_action:Literal['review','evaluate_for_roadmap','request_clarification','possible_duplicate','reject_as_abuse']
    scores:Scores
    confidence:float=Field(ge=0,le=1)
    human_review_required:bool=True
    rationale:List[str]

PLATFORM_RULES={
 'research_librarian':['research librarian','source card','research route','librarian','citation','source ranking'],
 'site_intelligence':['site intelligence','dashboard','indicator','analytics','source status','brief export'],
 'workbench':['workbench','calculator','equation','graph','model','simulation'],
 'decision_studio':['decision studio','decision brief','tradeoff','scenario brief'],
 'research_library':['article map','research library','library','article','content gap'],
 'platform_core':['accessibility','navigation','search','login','api','export','performance','mobile'],
}
TYPE_RULES={
 'bug_report':['bug','broken','error','does not work','not working','fails'],
 'calculator_request':['calculator','calculate','equation','simulation','modeling tool'],
 'data_source_request':['api source','dataset','data source','connector','indicator'],
 'content_request':['article','coverage','topic','research path','article map'],
 'integration_request':['integrate','integration','connect','handoff','deep link'],
 'ux_accessibility':['accessibility','screen reader','contrast','keyboard','mobile','navigation','usability'],
 'export_request':['export','pdf','csv','download','report'],
 'feature_request':['feature','add','support','ability','could you'],
}
TOPICS=['sustainability','governance','infrastructure','artificial intelligence','economics','risk','resilience','climate','energy','environment','psychology','mathematics','engineering','law','human rights','education','accessibility','data','research']
SENSITIVE=[('possible_personal_data',r'\b\d{3}-\d{2}-\d{4}\b|\b(?:\d[ -]*?){13,16}\b'),('possible_medical_information',r'\b(diagnosed|medication|patient|medical record)\b'),('possible_secret',r'\b(api[_ -]?key|password|secret token)\b')]
ABUSE=re.compile(r'\b(kill yourself|racial slur|spam spam spam)\b',re.I)

def auth(x_scfs_ai_key:Optional[str]):
    expected=os.getenv('SCFS_AI_API_KEY','')
    if expected and x_scfs_ai_key!=expected: raise HTTPException(401,'Invalid AI service key')

def text(s:Submission): return ' '.join([s.title,s.category,s.problem,s.suggestion,s.success_criteria,s.beneficiaries,s.implementation_notes,' '.join(s.product),' '.join(s.product_version),' '.join(s.component),' '.join(s.issue_type),' '.join(s.release)]).lower()
def choose(rules,t,default):
    ranked=sorted(((sum(1 for k in keys if k in t),name) for name,keys in rules.items()),reverse=True)
    return ranked[0][1] if ranked and ranked[0][0] else default

def deterministic(s:Submission)->Analysis:
    t=text(s); area=choose(PLATFORM_RULES,t,'platform_core'); ftype=choose(TYPE_RULES,t,'feature_request')
    topics=[x for x in TOPICS if x in t][:8] or ['platform development']
    flags=[name for name,pat in SENSITIVE if re.search(pat,t,re.I)]
    if ABUSE.search(t): flags.append('possible_abuse')
    urgency=5 if any(x in t for x in ['urgent','security','data loss','site down']) else 4 if any(x in t for x in ['broken','cannot','fails']) else 3
    impact=5 if any(x in t for x in ['all users','site-wide','critical','accessibility']) else 4 if len(s.beneficiaries)>20 else 3
    effort=5 if any(x in t for x in ['new platform','machine learning','real-time','complex survey']) else 4 if ftype in ['integration_request','calculator_request','data_source_request'] else 3
    action='reject_as_abuse' if 'possible_abuse' in flags else 'request_clarification' if len(s.problem.strip())<12 or len(s.suggestion.strip())<12 else 'evaluate_for_roadmap'
    digest=hashlib.sha256(re.sub(r'\W+',' ',t).encode()).hexdigest()[:16]
    words=re.findall(r'[a-z0-9]+',t); dup=[' '.join(sorted(set(words))[:12]),digest]
    summary=(s.suggestion or s.problem or s.title).strip(); summary=re.sub(r'\s+',' ',summary)[:320]
    confidence=min(.92,.58 + .05*sum(bool(x.strip()) for x in [s.problem,s.suggestion,s.success_criteria,s.beneficiaries]))
    return Analysis(submission_id=s.submission_id,analysis_id=hashlib.sha256((s.submission_id+ANALYSIS_VERSION).encode()).hexdigest()[:24],provider='deterministic',model='rules-v1',generated_at=datetime.now(timezone.utc).isoformat(),summary=summary,feature_type=ftype,platform_area=area,topics=topics,sentiment='negative' if any(x in t for x in ['frustrating','bad','broken']) else 'positive' if any(x in t for x in ['love','great','helpful']) else 'neutral',safety_flags=flags,duplicate_keys=dup,suggested_roadmap_destination=area,suggested_action=action,scores=Scores(urgency=urgency,impact=impact,effort=effort,strategic_alignment=.88 if area!='platform_core' else .72),confidence=confidence,rationale=[f'Classified as {ftype} from submission language.',f'Routed to {area} using platform terminology.','Scores are advisory and require human review.'])

def call_json(url,headers,payload):
    req=request.Request(url,data=json.dumps(payload).encode(),headers={'Content-Type':'application/json',**headers},method='POST')
    try:
        with request.urlopen(req,timeout=float(os.getenv('SCFS_AI_PROVIDER_TIMEOUT','25'))) as r:return json.loads(r.read())
    except (error.URLError,error.HTTPError,TimeoutError,json.JSONDecodeError) as e: raise RuntimeError(str(e))

def llm(s:Submission,base:Analysis)->Analysis:
    provider=os.getenv('SCFS_AI_PROVIDER','disabled').lower(); model=os.getenv('SCFS_AI_MODEL','')
    if provider in ('','disabled','deterministic'): return base
    prompt='Return only JSON matching this schema: '+json.dumps(Analysis.model_json_schema())+'\nSubmission:'+s.model_dump_json()+'\nDeterministic baseline:'+base.model_dump_json()
    try:
        if provider=='gemini':
            key=os.getenv('SCFS_GEMINI_API_KEY',''); model=model or 'gemini-2.5-flash'; data=call_json(f'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}',{}, {'contents':[{'parts':[{'text':prompt}]}],'generationConfig':{'responseMimeType':'application/json','temperature':.1}}); raw=data['candidates'][0]['content']['parts'][0]['text']
        elif provider in ('openai','deepseek'):
            key=os.getenv('SCFS_OPENAI_API_KEY' if provider=='openai' else 'SCFS_DEEPSEEK_API_KEY',''); model=model or ('gpt-4.1-mini' if provider=='openai' else 'deepseek-chat'); baseurl='https://api.openai.com/v1' if provider=='openai' else 'https://api.deepseek.com'; data=call_json(baseurl+'/chat/completions',{'Authorization':'Bearer '+key},{'model':model,'messages':[{'role':'system','content':'You classify feature feedback. Return JSON only.'},{'role':'user','content':prompt}],'temperature':.1,'response_format':{'type':'json_object'}}); raw=data['choices'][0]['message']['content']
        else:return base
        obj=json.loads(raw); obj.update({'submission_id':s.submission_id,'analysis_id':base.analysis_id,'analysis_version':ANALYSIS_VERSION,'provider':provider,'model':model,'generated_at':datetime.now(timezone.utc).isoformat(),'human_review_required':True})
        return Analysis.model_validate(obj)
    except Exception:
        base.rationale.append(f'{provider} provider was unavailable or invalid; deterministic fallback used.')
        return base

@app.get('/health')
def health():
    return {'ok':True,'service':'scfs-ai-triage','version':VERSION,'provider':os.getenv('SCFS_AI_PROVIDER','disabled'),'human_review_required':True}
@app.get('/v1/status')
def status(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key); p=os.getenv('SCFS_AI_PROVIDER','disabled'); return {'ok':True,'version':VERSION,'provider':p,'model':os.getenv('SCFS_AI_MODEL',''),'configured':p in ('disabled','deterministic') or bool(os.getenv({'gemini':'SCFS_GEMINI_API_KEY','deepseek':'SCFS_DEEPSEEK_API_KEY','openai':'SCFS_OPENAI_API_KEY'}.get(p,'')))}
@app.post('/v1/analyze',response_model=Analysis)
def analyze(s:Submission,x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key); return llm(s,deterministic(s))


@app.post('/v1/surveys/analyze', response_model=SurveyAnalysisResult)
def survey_analyze(payload: SurveyAnalysisRequest, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return analyze_survey(payload)

@app.get('/v1/surveys/methodology')
def survey_methodology(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {'ok':True,'analysis_version':'5.1.0-1','descriptive_statistics':True,'cross_tabs':True,'cronbach_alpha':True,'open_text_coding':'deterministic term-frequency','statistical_significance':False,'causal_inference':False,'human_review_required':True}


@app.get('/v1/platform/capabilities')
def platform_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'service': 'scfs-feedback-research-intelligence',
        'capabilities': ['product_support_platform','release_intelligence','release_readiness_scoring','feature_triage','documentation_feedback_intelligence','documentation_gap_scoring','case_relationship_intelligence','support_demand_opportunity_scoring','guided_resolution_ranking','unified_support_search','resolution_journey','support_discovery_fusion','error_signature_matching','known_issue_prioritization','private_support_handoff_schema','product_taxonomy_context','component_and_issue_context','release_context','support_knowledge_base_schema','support_article_records','known_issue_records','documentation_collections','related_suggestions_and_releases','editorial_governance','documentation_standards_scoring','controlled_publication_workflow','repository_release_synchronization','documentation_drift_detection','repository_link_health','support_reliability_scoring','support_reliability_trends','unresolved_query_clustering','reliability_report_integrity','cross_product_incident_impact','product_dependency_routing','cross_product_resolution_journeys','orchestration_report_integrity','connected_operations_scoring','connected_operations_action_planning','connected_operations_report_integrity','survey_descriptive_analysis','cross_tabs','scale_reliability','open_text_coding'],
        'providers': ['deterministic','gemini','deepseek','openai'],
        'human_review_required': True,
        'statistical_significance': False,
        'causal_inference': False,
    }


@app.get('/v1/knowledge-base/capabilities')
def knowledge_base_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-support-knowledge-base/1.0',
        'wordpress_source_of_truth': True,
        'content_types': ['support_article', 'known_issue'],
        'taxonomies': ['product', 'product_version', 'component', 'issue_type', 'release', 'documentation_collection', 'article_type'],
        'relationships': ['related_suggestions', 'related_releases'],
        'public_content_only': True,
        'private_suggestion_text_exposed': False,
    }


@app.post('/v1/guided-resolution/rank', response_model=ResolutionRankResult)
def guided_resolution_rank(payload: ResolutionRankRequest, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return rank_resolution(payload)

@app.get('/v1/guided-resolution/capabilities')
def guided_resolution_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-guided-resolution/1.0',
        'capabilities': ['deterministic_ranking','error_signature_matching','product_version_component_context','known_issue_prioritization','editorial_promotion'],
        'wordpress_source_of_truth': True,
        'private_case_storage': False,
        'human_review_required': True,
    }



@app.get('/v1/product-support/capabilities')
def product_support_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-product-support-platform/1.0',
        'capabilities': ['unified_support_overview','release_readiness_scoring','guided_resolution','knowledge_base','known_issue_prioritization','public_feedback','survey_research','private_support_handoff_boundary','embedded_view_switching','browser_history_navigation','anchored_fallback_links','product_context_preservation'],
        'wordpress_source_of_truth': True,
        'private_case_storage': False,
        'automatic_case_creation': False,
        'human_review_required': True,
    }


@app.post('/v1/product-support/overview', response_model=ProductSupportOverview)
def product_support_overview(payload: ProductSupportEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return summarize_product_support(payload)


@app.post('/v1/product-support/releases/score', response_model=ReleaseReadinessScore)
def product_support_release_score(payload: ReleaseReadinessEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return score_release_readiness(payload)

@app.get('/v1/documentation-intelligence/capabilities')
def documentation_intelligence_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-documentation-feature-intelligence/1.0',
        'capabilities': ['article_feedback_aggregation','failed_search_gap_detection','documentation_gap_scoring','case_to_article_relationships','case_to_suggestion_relationships','support_demand_scoring'],
        'wordpress_source_of_truth': True,
        'private_case_content_storage': False,
        'contact_details_storage': False,
        'human_review_required': True,
    }


@app.post('/v1/documentation-intelligence/gaps/score', response_model=DocumentationGapScore)
def documentation_gap_score(payload: DocumentationGapEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return score_documentation_gap(payload)


@app.post('/v1/documentation-intelligence/support-demand/score', response_model=SupportDemandScore)
def support_demand_score(payload: SupportDemandEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return score_support_demand(payload)


@app.get('/v1/support-content/capabilities')
def support_content_capabilities():
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-support-content-operations/1.1',
        'product_onboarding': True,
        'starter_content': True,
        'readme_import': True,
        'changelog_import': True,
        'json_import_export': True,
        'duplicate_detection': True,
        'freshness_validation': True,
        'import_batch_rollback': True,
        'malformed_source_inspection': True,
        'scheduled_validation_health': True,
        'export_checksum_integrity': True,
        'role_capability_boundary': 'manage_options',
        'human_review_required': True,
        'automatic_publication': False,
    }


@app.post('/v1/support-content/readiness/score', response_model=ProductSupportReadiness)
def support_content_readiness(payload: ProductOnboardingEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return score_product_readiness(payload)


@app.post('/v1/support-content/import/plan', response_model=ImportPlan)
def support_content_import_plan(payload: SourceDocument, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return plan_source_import(payload)


@app.post('/v1/support-content/import/inspect', response_model=ImportSourceInspection)
def support_content_import_inspect(payload: SourceDocument, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return inspect_source_document(payload)


@app.post('/v1/support-content/import/recovery', response_model=ImportRecoveryPlan)
def support_content_import_recovery(payload: ImportBatchEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return plan_import_recovery(payload)


@app.post('/v1/support-content/export/verify', response_model=ExportIntegrityResult)
def support_content_export_verify(payload: ExportIntegrityEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return verify_export_integrity(payload)

@app.get('/v1/editorial-governance/capabilities')
def editorial_governance_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-editorial-governance/1.0',
        'capabilities': [
            'author_reviewer_approver_assignments',
            'controlled_workflow_transitions',
            'documentation_standards_scoring',
            'version_specific_approval',
            'scheduled_publication',
            'expiration_and_review_reminders',
            'editorial_audit_history',
        ],
        'wordpress_source_of_truth': True,
        'automatic_approval': False,
        'private_editorial_comments_publicly_exposed': False,
        'human_review_required': True,
    }


@app.post('/v1/editorial-governance/transitions/evaluate', response_model=EditorialTransitionDecision)
def editorial_transition_evaluate(payload: EditorialTransitionEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return evaluate_editorial_transition(payload)


@app.post('/v1/editorial-governance/standards/score', response_model=DocumentationStandardsScore)
def editorial_standards_score(payload: DocumentationStandardsEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return score_documentation_standards(payload)


@app.post('/v1/editorial-governance/queue/summarize', response_model=EditorialGovernanceSummary)
def editorial_queue_summary(payload: EditorialQueueEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return summarize_editorial_queue(payload)

@app.get('/v1/repository-sync/capabilities')
def repository_sync_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-repository-release-synchronization/1.0',
        'providers': ['github_public'],
        'capabilities': [
            'repository_mapping',
            'release_ingestion_planning',
            'readme_and_changelog_ingestion',
            'documentation_drift_detection',
            'link_health_summarization',
            'approval_gated_draft_creation',
            'signed_webhook_queue',
        ],
        'wordpress_source_of_truth': True,
        'private_repository_sync': False,
        'automatic_approval': False,
        'automatic_publication': False,
        'human_review_required': True,
    }


@app.post('/v1/repository-sync/candidates/evaluate', response_model=RepositorySyncDecision)
def repository_sync_candidate_evaluate(payload: RepositoryCandidateEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return evaluate_repository_candidate(payload)


@app.post('/v1/repository-sync/drift/evaluate', response_model=RepositoryDriftResult)
def repository_sync_drift_evaluate(payload: RepositoryDriftEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return evaluate_repository_drift(payload)


@app.post('/v1/repository-sync/releases/plan', response_model=ReleaseSyncPlan)
def repository_sync_release_plan(payload: ReleaseSourceEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return plan_release_sync(payload)


@app.post('/v1/repository-sync/link-health/summarize', response_model=LinkHealthSummary)
def repository_sync_link_health(payload: LinkHealthEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return summarize_link_health(payload)



@app.get('/v1/support-reliability/capabilities')
def support_reliability_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-support-reliability-center/1.0',
        'capabilities': [
            'product_reliability_scoring',
            'support_resolution_rate_analysis',
            'documentation_usefulness_trends',
            'known_issue_recurrence',
            'release_readiness_aggregation',
            'unresolved_query_clustering',
            'documentation_gap_prioritization',
            'reliability_report_integrity',
        ],
        'wordpress_source_of_truth': True,
        'private_case_content_storage': False,
        'automatic_roadmap_change': False,
        'automatic_incident_declaration': False,
        'human_review_required': True,
    }


@app.post('/v1/support-reliability/score', response_model=ProductReliabilityScore)
def support_reliability_score(payload: ProductReliabilityEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return score_product_reliability(payload)


@app.post('/v1/support-reliability/trends/summarize', response_model=ReliabilityTrendSummary)
def support_reliability_trends(payload: ReliabilityTrendEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return summarize_reliability_trend(payload)


@app.post('/v1/support-reliability/clusters/prioritize', response_model=UnresolvedClusterPriority)
def support_reliability_cluster_priority(payload: UnresolvedClusterEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return prioritize_unresolved_cluster(payload)


@app.post('/v1/support-reliability/reports/verify', response_model=ReliabilityReportIntegrityResult)
def support_reliability_report_verify(payload: ReliabilityReportIntegrityEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return verify_reliability_report(payload)

@app.get('/v1/cross-product/capabilities')
def cross_product_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-cross-product-support-orchestration/1.0',
        'capabilities': [
            'cross_product_incident_impact',
            'product_dependency_graph',
            'shared_component_relationships',
            'related_product_recommendations',
            'cross_product_resolution_journeys',
            'orchestration_report_integrity',
        ],
        'wordpress_source_of_truth': True,
        'private_case_content_storage': False,
        'contact_identity_storage': False,
        'automatic_incident_declaration': False,
        'automatic_release_blocking': False,
        'automatic_private_case_creation': False,
        'human_review_required': True,
    }


@app.post('/v1/cross-product/incidents/evaluate', response_model=IncidentImpactResult)
def cross_product_incident_evaluate(payload: IncidentImpactEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return evaluate_incident_impact(payload)


@app.post('/v1/cross-product/routes/recommend', response_model=ProductRouteResult)
def cross_product_routes_recommend(payload: ProductRouteEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return recommend_product_routes(payload)


@app.post('/v1/cross-product/journeys/build', response_model=ResolutionJourneyResult)
def cross_product_journeys_build(payload: ResolutionJourneyEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return build_resolution_journey(payload)


@app.post('/v1/cross-product/reports/verify', response_model=OrchestrationReportResult)
def cross_product_reports_verify(payload: OrchestrationReportEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return verify_orchestration_report(payload)

@app.get('/v1/connected-operations/capabilities')
def connected_operations_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'schema': 'scfs-connected-product-support-operations/1.0',
        'capabilities': [
            'connected_module_readiness',
            'product_operations_scoring',
            'governed_action_planning',
            'operations_report_integrity',
            'specialist_module_source_of_truth_preservation',
        ],
        'wordpress_source_of_truth': True,
        'private_case_content_storage': False,
        'automatic_publication': False,
        'automatic_incident_declaration': False,
        'automatic_roadmap_change': False,
        'automatic_private_case_creation': False,
        'human_review_required': True,
    }


@app.post('/v1/connected-operations/readiness/score', response_model=ConnectedOperationsScore)
def connected_operations_readiness_score(payload: ConnectedOperationsEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return score_connected_operations(payload)


@app.post('/v1/connected-operations/actions/plan', response_model=OperationsActionPlan)
def connected_operations_action_plan(payload: OperationsActionEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return plan_connected_action(payload)


@app.post('/v1/connected-operations/reports/verify', response_model=OperationsReportResult)
def connected_operations_report_verify(payload: OperationsReportEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return verify_connected_operations_report(payload)


@app.get('/v1/support-article-integrity/capabilities')
def support_article_integrity_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': '5.2.8',
        'schema': 'scfs-support-article-integrity/1.0',
        'capabilities': [
            'content_completeness',
            'metadata_validation',
            'version_verification',
            'heading_hierarchy',
            'required_editorial_sections',
            'template_placeholder_detection',
            'link_integrity',
            'image_and_table_accessibility',
            'relationship_context',
            'freshness_and_review_due_dates',
            'publication_readiness_scoring',
        ],
        'wordpress_source_of_truth': True,
        'automatic_content_changes': False,
        'automatic_publication': False,
        'human_review_required': True,
    }


@app.post('/v1/support-article-integrity/assess', response_model=SupportArticleIntegrityResult)
def support_article_integrity_assess(payload: SupportArticleIntegrityEvidence, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return assess_support_article_integrity(payload)


@app.get('/v1/support-discovery/capabilities')
def support_discovery_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': '5.3.0',
        'schema': 'scfs-support-discovery/1.0',
        'capabilities': [
            'weighted_support_article_search',
            'synonym_expansion',
            'version_aware_matching',
            'deterministic_sorting',
            'no_results_recovery_support',
        ],
        'personal_data_stored': False,
        'automatic_content_changes': False,
        'human_review_required': False,
    }


@app.post('/v1/support-discovery/search', response_model=DiscoverySearchResult)
def support_discovery_search(payload: DiscoverySearchRequest, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return search_support_articles(payload)

@app.get('/v1/unified-support/capabilities')
def unified_support_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': '5.3.0',
        'schema': 'scfs-unified-support-search/1.0',
        'journey_schema': 'scfs-support-resolution-journey/1.0',
        'capabilities': [
            'guided_resolution_and_discovery_fusion',
            'known_issue_first_routing',
            'verified_guidance_routing',
            'release_context_routing',
            'public_improvement_context',
            'deterministic_resolution_journey',
            'consent_gated_private_support_boundary',
        ],
        'wordpress_source_of_truth': True,
        'personal_data_stored': False,
        'private_case_storage': False,
        'automatic_case_creation': False,
        'human_review_required': True,
    }


@app.post('/v1/unified-support/search', response_model=UnifiedSupportSearchResult)
def unified_support_search(payload: UnifiedSupportSearchRequest, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return search_unified_support(payload)


@app.post('/v1/unified-support/journey', response_model=UnifiedSupportSearchResult)
def unified_support_journey(payload: UnifiedSupportSearchRequest, x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return search_unified_support(payload)
