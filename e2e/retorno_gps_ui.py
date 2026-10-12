import re
from playwright.sync_api import sync_playwright
B="http://127.0.0.1:8000"
def run(b,geo,label,pid,foto=True):
    kw=dict(viewport={"width":390,"height":800})
    if geo: kw.update(geolocation={"latitude":-13.5226,"longitude":-71.9674},permissions=["geolocation"])
    ctx=b.new_context(**kw); pg=ctx.new_page(); errs=[]; pg.on("pageerror",lambda x:errs.append(str(x)[:120]))
    pg.goto(B+"/login"); pg.fill('input[name=email]',"trabajador@mssc.test"); pg.fill('input[name=password]',"password"); pg.click('button[type=submit]'); pg.wait_for_load_state("networkidle")
    pg.goto(f"{B}/papeletas/{pid}"); pg.wait_for_load_state("networkidle")
    pg.wait_for_timeout(500)
    if foto: pg.set_input_files("input[name=foto]","e2e/ok.png")
    pg.click("button:has-text('Usar mi ubicación')"); pg.wait_for_timeout(2500)
    vals=pg.evaluate("[...document.querySelectorAll('input[name=latitud],input[name=longitud]')].map(e=>e.name+'='+e.value)")
    msg=[t for t in pg.locator("[role=alert], .text-red-600, .text-rojo-600, [class*=error]").all_inner_texts() if t.strip()][:2]
    btn=pg.locator("button:has-text('Confirmar retorno')")
    if btn.is_disabled():
        print(label,'| coords:',vals,'| avisos:',msg,'| boton Confirmar DESHABILITADO (bien) | JS:',errs); pg.screenshot(path=f'/tmp/shots/retorno_{label}.png'); ctx.close(); return
    btn.click(); pg.wait_for_load_state("networkidle"); pg.wait_for_timeout(1200)
    body=pg.inner_text("body")
    print(label,"| coords:",vals,"| avisos:",msg,"| resultado:",re.findall(r"(Finalizada|Retorno registrado|AutorizadaYCorriendo|Autorizada[^\n]{0,15})",body)[:2],"| JS:",errs)
    pg.screenshot(path=f"/tmp/shots/retorno_{label}.png"); ctx.close()
with sync_playwright() as p:
    b=p.chromium.launch()
    run(b,False,"sin_gps",9)
    run(b,True,"con_gps",9)
    b.close()
