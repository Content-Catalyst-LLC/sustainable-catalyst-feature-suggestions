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

VERSION='4.1.1'
ANALYSIS_VERSION='4.1.1-1'
app=FastAPI(title='Sustainable Catalyst Feature Suggestions AI',version=VERSION)

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
    return {'ok':True,'analysis_version':'4.1.1-1','descriptive_statistics':True,'cross_tabs':True,'cronbach_alpha':True,'open_text_coding':'deterministic term-frequency','statistical_significance':False,'causal_inference':False,'human_review_required':True}


@app.get('/v1/platform/capabilities')
def platform_capabilities(x_scfs_ai_key:Optional[str]=Header(default=None)):
    auth(x_scfs_ai_key)
    return {
        'ok': True,
        'version': VERSION,
        'service': 'scfs-feedback-research-intelligence',
        'capabilities': ['product_support_platform','release_intelligence','release_readiness_scoring','feature_triage','documentation_feedback_intelligence','documentation_gap_scoring','case_relationship_intelligence','support_demand_opportunity_scoring','guided_resolution_ranking','error_signature_matching','known_issue_prioritization','private_support_handoff_schema','product_taxonomy_context','component_and_issue_context','release_context','support_knowledge_base_schema','support_article_records','known_issue_records','documentation_collections','related_suggestions_and_releases','survey_descriptive_analysis','cross_tabs','scale_reliability','open_text_coding'],
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
