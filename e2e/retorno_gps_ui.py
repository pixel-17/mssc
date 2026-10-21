import re
from playwright.sync_api import sync_playwright
B="http://127.0.0.1:8000"
def run(b,geo,label,pid):
    kw=dict(viewport={"width":390,"height":800},permissions=["camera"])
    if geo: kw.update(geolocation={"latitude":-13.5226,"longitude":-71.9674},permissions=["geolocation","camera"])
    ctx=b.new_context(**kw); pg=ctx.new_page(); errs=[]; pg.on("pageerror",lambda x:errs.append(str(x)[:120]))
    pg.goto(B+"/login"); pg.fill('input[name=email]',"trabajador@mssc.test"); pg.fill('input[name=password]',"password"); pg.click('button[type=submit]'); pg.wait_for_load_state("networkidle")
    pg.goto(f"{B}/papeletas/{pid}"); pg.wait_for_load_state("networkidle")
    pg.wait_for_timeout(500)
    # Único botón: abre cámara en vivo (fake device vía --use-fake-device-for-media-stream) + ubicación.
    pg.click("button:has-text('Tomar foto de retorno')"); pg.wait_for_timeout(2500)
    vals=pg.evaluate("[...document.querySelectorAll('input[name=latitud],input[name=longitud]')].map(e=>e.name+'='+e.value)")
    msg=[t for t in pg.locator("[role=alert], .text-red-600, .text-rojo-600, [class*=error]").all_inner_texts() if t.strip()][:2]
    btn=pg.locator("button:has-text('Capturar foto'), button:has-text('Esperando ubicación')")
    if btn.is_disabled():
        print(label,'| coords:',vals,'| avisos:',msg,'| boton Capturar foto DESHABILITADO (bien) | JS:',errs); pg.screenshot(path=f'/tmp/shots/retorno_{label}.png'); ctx.close(); return
    btn.click(); pg.wait_for_timeout(500)
    pg.click("button:has-text('Confirmar retorno')"); pg.wait_for_load_state("networkidle"); pg.wait_for_timeout(1200)
    body=pg.inner_text("body")
    print(label,"| coords:",vals,"| avisos:",msg,"| resultado:",re.findall(r"(Finalizada|Retorno registrado|AutorizadaYCorriendo|Autorizada[^\n]{0,15})",body)[:2],"| JS:",errs)
    pg.screenshot(path=f"/tmp/shots/retorno_{label}.png"); ctx.close()
with sync_playwright() as p:
    # Flags de Chromium para simular una cámara real sin hardware (frames sintéticos).
    b=p.chromium.launch(args=["--use-fake-device-for-media-stream","--use-fake-ui-for-media-stream"])
    run(b,False,"sin_gps",9)
    run(b,True,"con_gps",9)
    b.close()