import pandas as pd, re, json, math

x = pd.read_excel('lista.ods', engine='odf', sheet_name=None, header=None)
mat = x['Tabella1']; jobs = x['Tabella5']

def s(v): return '' if (isinstance(v,float) and math.isnan(v)) or v is None else str(v).strip()

def num(v):
    """first number in a string, EU format -> float; floats pass through"""
    if isinstance(v,(int,float)) and not (isinstance(v,float) and math.isnan(v)): return float(v)
    t = s(v)
    m = re.search(r'(\d+(?:[.,]\d+)?)', t)
    if not m: return None
    return float(m.group(1).replace(',', '.'))

def all_nums(v):
    return [float(n.replace(',','.')) for n in re.findall(r'(\d+(?:[.,]\d+)?)', s(v))]

def resa(v):
    t = s(v)
    if not t or t == '/': return (None,None,None)
    unit = 'kg' if 'kg' in t.lower() else ('m' if re.search(r'a\s*mt', t) else 'lt')
    ns = all_nums(t)
    if not ns: return (None,None,None)
    if '/' in t and len(ns)>=2: return (ns[0],ns[1],unit)
    if '-' in t and len(ns)>=2: return (ns[0],ns[1],unit)
    return (ns[0],ns[0],unit)

def package(name):
    m = re.search(r'(\d+(?:[.,]\d+)?)\s*(lt|l\b|kg)', name, re.I)
    if m:
        size = float(m.group(1).replace(',','.')); u = 'kg' if m.group(2).lower()=='kg' else 'lt'
        label = f"{'sacco' if u=='kg' else ('bidone' if size>=10 else ('latta' if size>=4 else 'barattolo'))} {m.group(1)} {u}"
        return size,u,label
    m = re.search(r'(\d+(?:[.,]\d+)?)\s*x\s*(\d+(?:[.,]\d+)?)\s*m', name, re.I)
    if m: return 1.0,'pc',f"telo {m.group(1)}x{m.group(2)} m"
    if re.search(r'rotolo', name, re.I): return 1.0,'pc','rotolo'
    if re.search(r'pacco 100', name, re.I): return 100.0,'pc','pacco da 100'
    return 1.0,'pc','pezzo'

def discount(v):
    t = s(v)
    if not t or t=='/': return None
    if isinstance(v,float) and v<1: return f"{round(v*100)}%"
    return t

section_map = {
 'PITTURE e TINTE MURALI': None, 'SMALTI E IMPREGNANTI': None,
 'PRODOTTI e STUCCHI': 'filler', 'COPERTURE e CONSUMABILI': 'consumable',
 'RULLI e STRUMENTI': 'tool', 'VARIE': 'consumable'}
sub_map = {'Interni':'paint_interior','Esterni':'paint_exterior','Smalti':'enamel','Acqua':'wood','Sintetico':'enamel'}

materials=[]; review=[]
section=None; cat=None
for i,row in mat.iterrows():
    name=s(row[0]); 
    if not name: continue
    rest=[s(row[k]) for k in range(1,6)]
    if not any(rest):
        if name in section_map: section=name; cat=section_map[name]
        elif name in sub_map: cat=sub_map[name]
        else: review.append({'row':int(i),'name':name,'issue':'header non riconosciuto'})
        continue
    c = cat
    lname=name.lower()
    if any(k in lname for k in ['diluente','converti ruggine']): c='solvent'
    if 'fissativo' in lname or 'neofix' in lname: c='primer'
    if any(k in lname for k in ['pulitore','combat 444','schiuma','silicone','guanti','vaschett','secchio']): c='consumable'
    if any(k in lname for k in ['cartavetro','fratazzo','spatole']): c = 'consumable' if 'cartavetro' in lname else 'tool'
    if 'ferromicaceo' in lname or 'fumex' in lname or 'clorocauc' in lname: c='enamel'
    if 'impregnante' in lname or lname.startswith('imp.') or 'borma' in lname or 'extramarine' in lname: c='wood'
    kind = 'tool' if c=='tool' else ('consumable_checklist' if c=='consumable' else 'consumable_quantity')
    size,unit,label = package(name)
    rmin,rmax,runit = resa(row[5])
    if runit=='kg' and unit=='lt': unit='kg'
    cost = num(row[4])
    cost_no_vat = num(row[3])
    plus_sped = '+ sped' in s(row[4]).replace(' ','').replace('+sped','+ sped') or 'sped' in s(row[4])
    if cost is None and cost_no_vat and cost_no_vat>0: cost=round(cost_no_vat*1.22,2); review.append({'row':int(i),'name':name,'issue':'prezzo +IVA mancante, calcolato da -IVA'})
    if cost is None: review.append({'row':int(i),'name':name,'issue':'prezzo mancante'})
    if kind=='consumable_quantity' and rmin is None: review.append({'row':int(i),'name':name,'issue':'resa mancante'})
    if len(all_nums(row[4]))>=2: review.append({'row':int(i),'name':name,'issue':f"due prezzi nel foglio: {s(row[4])} (bianco / tinta?) — preso il primo"})
    if cost==0: cost=None; review.append({'row':int(i),'name':name,'issue':'prezzo a 0 nel foglio'})
    if 'wallcream' in lname: size,unit,label=14.0,'lt','bidone 14 lt'
    if 'radiatori' in lname: c='enamel'; kind='consumable_quantity'
    if 'nastro' in lname: rmin=rmax=None
    if 'fratazzo' in lname: size,unit,label=1.0,'pc','pezzo'
    if 'secchio' in lname: size,unit,label=1.0,'pc','pezzo'
    if 'converti ruggine' in lname: review.append({'row':int(i),'name':name,'issue':'listino con due valori (€ 36,46/€ 52,08), prezzo +IVA mancante'})
    materials.append({
        'name': re.sub(r'\s+',' ',name.replace('"',' ').replace('  ',' ')).replace(' ,',',').strip(),
        'category': c, 'kind': kind, 'base_unit': unit,
        'package_size': size, 'package_label': label,
        'list_price': num(row[1]), 'discount_notes': discount(row[2]),
        'cost_price': cost, 'shipping_excluded': bool(plus_sped),
        'coverage_min': rmin, 'coverage_max': rmax,
        'supplier': 'Edilzone' if 'edilzone' in lname else ('Amazon' if 'amazon' in lname else None),
        'min_stock': 0, 'source_row': int(i)})

# jobs
unit_map={'Mq':'sqm','Coppia':'pair'}
jobs_out=[]
active_names={'Imbiancatura','Lavabili','Ringhiere "Piene"','RasatureCon Stucco fibrato','Rasatura Con Stucco Gesso','Impregnante'}
for i,row in jobs.iterrows():
    if i==0: continue
    name=s(row[0]); 
    if not name: continue
    u=s(row[1]); unit = unit_map.get(u.split()[0] if u else '', 'sqm')
    price = all_nums(row[4]); price = price[0] if price else None
    jobs_out.append({'name':re.sub(r'(?<=[a-z])(?=[A-Z])',' ',name).replace('"',''),
        'unit':unit,'unit_price':price,'default_coats':2 if unit in('sqm','pair') else None,
        'avg_time_notes':s(row[5]) or None,'avg_cost_per_100sqm_sheet':num(row[2]),
        'coverage_sheet':s(row[3]) or None,'active': name in active_names,'source_row':int(i)})
jobs_out.append({'name':'Stuccature','unit':'hour','unit_price':50.0,'default_coats':None,'avg_time_notes':None,
    'avg_cost_per_100sqm_sheet':None,'coverage_sheet':None,'active':True,'source_row':None,'note':'dal preventivo Belissi, non nel foglio'})

json.dump(materials,open('seed/materials.json','w'),ensure_ascii=False,indent=2)
json.dump(jobs_out,open('seed/jobs.json','w'),ensure_ascii=False,indent=2)
json.dump(review,open('seed/review.json','w'),ensure_ascii=False,indent=2)
print(len(materials),'materiali',len(jobs_out),'lavorazioni',len(review),'da rivedere')
