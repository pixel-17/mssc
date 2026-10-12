import sys, json
from playwright.sync_api import sync_playwright
B="http://127.0.0.1:8000"
roles={"admin":["dashboard","sedes","motivos","turnos","turnos/administrar","turnos/calendario","organigrama","unidades-organicas","configuraciones","usuarios-admin","usuarios-admin/crear","catalogos","admin/papeletas","reportes/horas-acumuladas","reportes/trabajador","reportes/sustentos","reportes/decisiones-rrhh","reportes/resumen-papeletas","user/profile"],
"rrhh":["dashboard","rrhh/papeletas","rrhh/abandonos","reportes/horas-acumuladas","turnos/calendario","usuarios","usuarios/crear"],
"jefearea":["dashboard","jefe/papeletas","papeletas","papeletas/crear","usuarios","usuarios/crear","turnos/calendario","organigrama","turnos/calendario/mio"],
"trabajador":["dashboard","papeletas","papeletas/crear","turnos/calendario/mio","user/profile"]}
res=[]
with sync_playwright() as p:
    b=p.chromium.launch()
    for role,pages in roles.items():
        print("== rol",role,flush=True)
        for vp,(w,h) in {"desk":(1280,800),"mob":(375,740)}.items():
            ctx=b.new_context(viewport={"width":w,"height":h}); pg=ctx.new_page()
            errs=[]; pg.on("console",lambda m,e=errs: e.append(("console",m.text[:140])) if m.type=="error" else None)
            pg.on("pageerror",lambda x,e=errs: e.append(("pageerror",str(x)[:140])))
            pg.on("response",lambda r,e=errs: e.append(("http%d"%r.status,r.url[-70:])) if r.status>=400 else None)
            pg.on("requestfailed",lambda r,e=errs: e.append(("reqfail",r.url[-70:])))
            pg.goto(B+"/login"); pg.fill('input[name=email]',f"{role}@mssc.test"); pg.fill('input[name=password]',"password"); pg.click('button[type=submit]'); pg.wait_for_load_state("networkidle")
            if "/login" in pg.url: res.append((role,vp,"LOGIN FALLÓ",pg.url)); ctx.close(); continue
            for path in pages:
                errs.clear(); r=pg.goto(f"{B}/{path}"); pg.wait_for_load_state("networkidle")
                ov=pg.evaluate("document.documentElement.scrollWidth - window.innerWidth")
                st=r.status if r else 0
                if errs or ov>2 or st>=400 or "/login" in pg.url:
                    res.append((role,vp,path,st,"overflow=%d"%ov if ov>2 else "",pg.url.replace(B,""),errs[:3]))
                if vp=="mob" and role in("trabajador","admin") and path in("dashboard","papeletas/crear","organigrama"): pg.screenshot(path=f"/tmp/shots/{role}_{path.replace('/','_')}_{vp}.png")
                if vp=="desk" and path in("dashboard","papeletas/crear","organigrama","turnos/administrar"): pg.screenshot(path=f"/tmp/shots/{role}_{path.replace('/','_')}_{vp}.png")
            ctx.close()
    b.close()
for r in res: print(r, flush=True)
print("TOTAL con hallazgos:",len(res))
