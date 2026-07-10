from fastapi.testclient import TestClient
from app.main import app
c=TestClient(app)

def payload():
    return {"instrument_id":"survey-1","instrument_title":"Workbench survey","schema_revision":2,"segment_by":"role","fields":[
      {"key":"role","label":"Role","type":"radio","options":["Researcher","Planner"]},
      {"key":"rating","label":"Usefulness","type":"rating","options":["1","2","3","4","5"],"scale_group":"experience"},
      {"key":"trust","label":"Trust","type":"likert","options":["1","2","3","4","5"],"scale_group":"experience"},
      {"key":"comment","label":"Comment","type":"textarea"}],
      "responses":[
        {"response_id":"1","answers":{"role":"Researcher","rating":"5","trust":"4","comment":"Clear useful calculator and strong graphs"}},
        {"response_id":"2","answers":{"role":"Planner","rating":"3","trust":"3","comment":"Needs clearer exports and better guidance"}},
        {"response_id":"3","answers":{"role":"Researcher","rating":"4","trust":"4","comment":"Useful graphs and clear guidance"}}]}

def test_survey_analysis():
    r=c.post('/v1/surveys/analyze',json=payload()); assert r.status_code==200
    j=r.json(); assert j['response_count']==3; assert j['human_review_required'] is True
    assert any(q['key']=='rating' and q['mean']==4.0 for q in j['questions'])
    assert j['text_intelligence'][0]['themes']; assert j['reliability'][0]['scale_group']=='experience'

def test_methodology():
    j=c.get('/v1/surveys/methodology').json(); assert j['statistical_significance'] is False
