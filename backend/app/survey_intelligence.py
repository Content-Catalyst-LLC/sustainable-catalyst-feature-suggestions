from __future__ import annotations
import math, re, hashlib
from collections import Counter, defaultdict
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
from pydantic import BaseModel, Field

SURVEY_ANALYSIS_VERSION = "4.4.0-1"
STOPWORDS = {"the","and","for","that","this","with","from","have","was","were","are","but","not","you","your","our","they","their","would","could","should","into","about","there","what","when","where","which","also","very","more","less","some","than","then","because","been","being","has","had","its","it's","just","really","survey","response","responses"}

class SurveyField(BaseModel):
    key: str
    label: str
    type: str = "text"
    options: List[str] = []
    scale_group: Optional[str] = None

class SurveyResponse(BaseModel):
    response_id: str
    submitted_at: Optional[str] = None
    answers: Dict[str, Any] = {}

class SurveyAnalysisRequest(BaseModel):
    instrument_id: str
    instrument_title: str
    schema_revision: int = 1
    fields: List[SurveyField]
    responses: List[SurveyResponse]
    segment_by: Optional[str] = None

class SurveyAnalysisResult(BaseModel):
    analysis_id: str
    analysis_version: str = SURVEY_ANALYSIS_VERSION
    generated_at: str
    instrument_id: str
    instrument_title: str
    response_count: int
    field_count: int
    completion: Dict[str, Any]
    questions: List[Dict[str, Any]]
    cross_tabs: List[Dict[str, Any]]
    reliability: List[Dict[str, Any]]
    text_intelligence: List[Dict[str, Any]]
    narrative_summary: List[str]
    warnings: List[str]
    methodology: Dict[str, Any]
    human_review_required: bool = True

def _values(value: Any) -> List[str]:
    if value is None: return []
    if isinstance(value, list): return [str(v).strip() for v in value if str(v).strip()]
    text=str(value).strip()
    return [text] if text else []

def _numeric(value: Any) -> Optional[float]:
    try:
        vals=_values(value)
        return float(vals[0]) if vals else None
    except (ValueError, TypeError): return None

def _mean(xs: List[float]) -> Optional[float]: return sum(xs)/len(xs) if xs else None

def _variance(xs: List[float]) -> float:
    if len(xs)<2: return 0.0
    m=sum(xs)/len(xs)
    return sum((x-m)**2 for x in xs)/(len(xs)-1)

def cronbach_alpha(rows: List[List[float]]) -> Optional[float]:
    if len(rows)<2 or not rows or len(rows[0])<2: return None
    k=len(rows[0]); item_vars=sum(_variance([r[i] for r in rows]) for i in range(k)); totals=[sum(r) for r in rows]; total_var=_variance(totals)
    if total_var<=0: return None
    return (k/(k-1))*(1-(item_vars/total_var))

def analyze_survey(req: SurveyAnalysisRequest) -> SurveyAnalysisResult:
    fields={f.key:f for f in req.fields}; n=len(req.responses); answered_counts=Counter(); questions=[]; warnings=[]
    for r in req.responses:
        for k,v in r.answers.items():
            if _values(v): answered_counts[k]+=1
    per_response=[sum(1 for f in req.fields if _values(r.answers.get(f.key))) for r in req.responses]
    completion={
        "average_answered_fields": round(_mean(per_response) or 0,2),
        "average_completion_rate": round(((_mean(per_response) or 0)/max(1,len(req.fields)))*100,1),
        "complete_response_count": sum(1 for x in per_response if x==len(req.fields)),
        "partial_response_count": sum(1 for x in per_response if 0<x<len(req.fields)),
        "question_answer_rates": {f.key: round(answered_counts[f.key]/n*100,1) if n else 0 for f in req.fields},
    }
    text_results=[]
    for f in req.fields:
        raw=[r.answers.get(f.key) for r in req.responses]
        vals=[v for item in raw for v in _values(item)]
        item={"key":f.key,"label":f.label,"type":f.type,"answered":answered_counts[f.key],"missing":max(0,n-answered_counts[f.key]),"answer_rate":round(answered_counts[f.key]/n*100,1) if n else 0}
        if f.type in {"number","rating","likert"}:
            nums=[x for v in raw if (x:=_numeric(v)) is not None]
            if nums: item.update({"mean":round(_mean(nums),3),"min":min(nums),"max":max(nums),"standard_deviation":round(math.sqrt(_variance(nums)),3),"n_numeric":len(nums)})
            counts=Counter(vals); item["distribution"]=[{"value":k,"count":v,"percent":round(v/max(1,len(vals))*100,1)} for k,v in counts.most_common()]
        elif f.type in {"select","radio","checkbox","consent","date"}:
            counts=Counter(vals); item["distribution"]=[{"value":k,"count":v,"percent":round(v/max(1,len(vals))*100,1)} for k,v in counts.most_common()]
        elif f.type in {"text","textarea"}:
            texts=[x for x in vals if x]
            token_counts=Counter()
            for t in texts:
                token_counts.update(w for w in re.findall(r"[a-z][a-z0-9'-]{2,}",t.lower()) if w not in STOPWORDS)
            themes=[{"term":term,"mentions":count,"response_share":round(count/max(1,len(texts))*100,1)} for term,count in token_counts.most_common(12)]
            examples=[re.sub(r"\s+"," ",t)[:240] for t in texts[:3]]
            text_results.append({"key":f.key,"label":f.label,"response_count":len(texts),"themes":themes,"illustrative_excerpts":examples,"method":"deterministic term frequency; review context before interpretation","confidence":round(min(.9,.35+len(texts)/50),2)})
            item["average_length_words"]=round(_mean([len(t.split()) for t in texts]) or 0,1)
        questions.append(item)
    cross_tabs=[]
    segment=req.segment_by if req.segment_by in fields else None
    if segment:
        seg_values=sorted({v for r in req.responses for v in _values(r.answers.get(segment))})[:20]
        for f in req.fields:
            if f.key==segment or f.type not in {"select","radio","rating","likert","consent"}: continue
            table=[]
            for sv in seg_values:
                subset=[r for r in req.responses if sv in _values(r.answers.get(segment))]
                counts=Counter(v for r in subset for v in _values(r.answers.get(f.key)))
                table.append({"segment":sv,"n":len(subset),"values":dict(counts)})
            cross_tabs.append({"segment_field":segment,"question_key":f.key,"question_label":f.label,"table":table,"descriptive_only":True})
    groups=defaultdict(list)
    for f in req.fields:
        if f.scale_group and f.type in {"rating","likert","number"}: groups[f.scale_group].append(f.key)
    reliability=[]
    for group,keys in groups.items():
        rows=[]
        for r in req.responses:
            nums=[_numeric(r.answers.get(k)) for k in keys]
            if all(x is not None for x in nums): rows.append([float(x) for x in nums])
        alpha=cronbach_alpha(rows)
        reliability.append({"scale_group":group,"items":keys,"complete_cases":len(rows),"cronbach_alpha":round(alpha,3) if alpha is not None else None,"interpretation":"internal consistency estimate; not proof of validity"})
    if n<10: warnings.append("Very small sample: distributions and themes may be unstable.")
    elif n<30: warnings.append("Small sample: interpret subgroup comparisons cautiously.")
    if segment and any(row["n"]<5 for ct in cross_tabs for row in ct["table"]): warnings.append("Some cross-tab cells contain fewer than five responses; do not generalize from them.")
    low=[q["label"] for q in questions if q["answer_rate"]<60]
    if low: warnings.append("Low response coverage for: "+", ".join(low[:5]))
    narrative=[]
    if n: narrative.append(f"{n} responses were analyzed across {len(req.fields)} fields; average field completion was {completion['average_completion_rate']}%.")
    top_missing=sorted(questions,key=lambda q:q['answer_rate'])[:3]
    if top_missing: narrative.append("The lowest answer coverage occurred for "+", ".join(q['label'] for q in top_missing)+".")
    for q in questions:
        if q.get('distribution'):
            top=q['distribution'][0]; narrative.append(f"For {q['label']}, the most common recorded answer was {top['value']} ({top['count']} responses).")
            if len(narrative)>=5: break
    digest=hashlib.sha256((req.instrument_id+str(req.schema_revision)+str(n)).encode()).hexdigest()[:24]
    return SurveyAnalysisResult(analysis_id=digest,generated_at=datetime.now(timezone.utc).isoformat(),instrument_id=req.instrument_id,instrument_title=req.instrument_title,response_count=n,field_count=len(req.fields),completion=completion,questions=questions,cross_tabs=cross_tabs,reliability=reliability,text_intelligence=text_results,narrative_summary=narrative,warnings=warnings,methodology={"quantitative":"descriptive statistics computed deterministically","text":"deterministic token-frequency coding with illustrative excerpts","inference":"no statistical significance or causal claims generated","schema_revision":req.schema_revision},human_review_required=True)
