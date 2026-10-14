import re
from playwright.sync_api import sync_playwright
B="http://127.0.0.1:8000"
def login(b,email,**kw):
    ctx=b.new_context(**kw); pg=ctx.new_page(); errs=[]
    pg.on("pageerror",lambda x:errs.append(str(x)[:120]))
    pg.goto(B+"/login"); pg.fill('input[name=email]',email); pg.fill('input[name=password]',"password"); pg.click('button[type=submit]'); pg.wait_for_load_state("networkidle")
    return ctx,pg,errs
with sync_playwright() as p:
    b=p.chromium.launch()
    ctx,pg,errs=login(b,"trabajador@mssc.test",geolocation={"latitude":-13.5226,"longitude":-71.9674},permissions=["geolocation"],viewport={"width":390,"height":800})
    pg.goto(B+"/papeletas/crear"); pg.wait_for_load_state("networkidle")
    print("form:",[ (e.evaluate("e=>e.tagName+':'+(e.name||e.getAttribute('wire:model')||e.type)")) for e in pg.locator("select,textarea,input:not([type=hidden]),button").all()][:12])
    pg.select_option("select",label="Particular"); pg.fill("textarea","Trámite bancario (prueba UI)")
    pg.click("text=Crear papeleta"); pg.wait_for_load_state("networkidle"); pg.wait_for_timeout(800)
    print("tras crear ->",pg.url.replace(B,""), "| estado visible:", re.findall(r"Pendiente [A-Za-z]+",pg.inner_text("body"))[:1])
    pid=re.search(r"/papeletas/(\d+)",pg.url).group(1)
    j_ctx,jp,jerr=login(b,"jefeinmediato@mssc.test"); jp.goto(f"{B}/jefe/papeletas/{pid}"); jp.wait_for_load_state("networkidle")
    jp.click("button:has-text('Aprobar')"); jp.wait_for_timeout(600)
    if jp.locator("button:has-text('Confirmar'), button:has-text('Sí')").count(): jp.locator("button:has-text('Confirmar'), button:has-text('Sí')").first.click()
    jp.wait_for_load_state("networkidle"); jp.wait_for_timeout(800); print("jefe ->",re.findall(r"(Pendiente [A-Za-z]+|Autorizada[A-Za-z ]*)",jp.inner_text("body"))[:2])
    r_ctx,rp,rerr=login(b,"rrhh@mssc.test"); rp.goto(f"{B}/rrhh/papeletas/{pid}"); rp.wait_for_load_state("networkidle")
    rp.click("button:has-text('Aprobar')"); rp.wait_for_timeout(600)
    if rp.locator("button:has-text('Confirmar'), button:has-text('Sí')").count(): rp.locator("button:has-text('Confirmar'), button:has-text('Sí')").first.click()
    rp.wait_for_load_state("networkidle"); rp.wait_for_timeout(800); print("rrhh ->",re.findall(r"(Pendiente [A-Za-z]+|Autorizada[A-Za-z ]*|Corriendo)",rp.inner_text("body"))[:2])
    pg.goto(f"{B}/papeletas/{pid}"); pg.wait_for_load_state("networkidle"); pg.wait_for_timeout(1500)
    print("trabajador ve:", re.findall(r"(Autorizada[A-Za-z ]*|Corriendo|Marcar retorno|Registrar retorno|Retorno)",pg.inner_text("body"))[:4])
    print("controles retorno:",[e.evaluate("e=>e.tagName+':'+(e.name||e.type||'')+':'+(e.innerText||'').slice(0,25)") for e in pg.locator("input[type=file], button").all()][:10])
    pg.screenshot(path="/tmp/shots/flujo_trabajador_corriendo.png")
    print("errores JS:",errs,jerr,rerr)
    b.close()
