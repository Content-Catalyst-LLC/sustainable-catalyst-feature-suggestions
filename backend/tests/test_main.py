from fastapi.testclient import TestClient
from app.main import app
c=TestClient(app)
def sample(): return {'submission_id':'abc','title':'Add a Workbench calculator','category':'New platform module','priority':'High','problem':'Readers cannot model infrastructure risk.','suggestion':'Add an infrastructure interdependency calculator.','success_criteria':'It produces validated scenarios.','beneficiaries':'Researchers and planners.'}
def test_health(): assert c.get('/health').json()['ok'] is True
def test_analyze():
 r=c.post('/v1/analyze',json=sample()); assert r.status_code==200; j=r.json(); assert j['platform_area']=='workbench'; assert j['human_review_required'] is True; assert 0<=j['confidence']<=1


def test_platform_capabilities():
    response = c.get('/v1/platform/capabilities')
    assert response.status_code == 200
    data = response.json()
    assert data['version'] == '3.1.0'
    assert data['human_review_required'] is True
    assert 'survey_descriptive_analysis' in data['capabilities']


def test_analyze_with_product_context():
    payload = sample()
    payload.update({'product':['Knowledge Library'],'product_version':['v2.5.0'],'component':['Search and Discovery'],'issue_type':['Feature Request'],'release':['v3.2.0']})
    response = c.post('/v1/analyze', json=payload)
    assert response.status_code == 200
    assert response.json()['analysis_version'] == '3.1.0-1'
