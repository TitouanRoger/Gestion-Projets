// ============================================================
// UPLOAD_BATCH.JS - UPLOAD DE FICHIERS PAR LOTS
// ============================================================
// Contourne la limite PHP (max_file_uploads) en envoyant par lots.
// - uploadBatch: envoie un FormData et gère la progression + retry 400
// - uploadFilesBatch: segmente un tableau de fichiers en lots de 10
// - uploadFolderBatch: idem mais avec chemins relatifs conservés
// ============================================================
// Système d'upload par batch pour contourner la limite PHP max_file_uploads
function uploadBatch(formData, projectId, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                onProgress(percent);
            }
        });
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const r = JSON.parse(xhr.responseText);
                    resolve(r);
                } catch (e) {
                    console.error('Parse error:', xhr.responseText);
                    reject(new Error('Parse error'));
                }
            } else if (xhr.status === 400) {
                console.warn('HTTP 400 reçu; tentative de retry après court délai');
                // Retry unique après 250ms (conditions de course après suppression)
                setTimeout(() => {
                    const retryXhr = new XMLHttpRequest();
                    retryXhr.upload.addEventListener('progress', (e2) => {
                        if (e2.lengthComputable) {
                            const percent2 = Math.round((e2.loaded / e2.total) * 100);
                            onProgress(percent2);
                        }
                    });
                    retryXhr.addEventListener('load', () => {
                        if (retryXhr.status === 200) {
                            try {
                                const r2 = JSON.parse(retryXhr.responseText);
                                resolve(r2);
                            } catch (e2) {
                                console.error('Parse error (retry):', retryXhr.responseText);
                                reject(new Error('Parse error'));
                            }
                        } else {
                            console.error('HTTP error (retry):', retryXhr.status, retryXhr.responseText);
                            reject(new Error('HTTP ' + retryXhr.status));
                        }
                    });
                    retryXhr.addEventListener('error', (e2) => {
                        console.error('Network error (retry):', e2);
                        reject(new Error('Network error'));
                    });
                    retryXhr.open('POST', 'assets/php/code_repo.php?action=upload');
                    retryXhr.send(formData);
                }, 250);
            } else {
                console.error('HTTP error:', xhr.status, xhr.responseText);
                reject(new Error('HTTP ' + xhr.status));
            }
        });
        xhr.addEventListener('error', (e) => {
            console.error('Network error:', e);
            reject(new Error('Network error'));
        });
        // Garder action dans l'URL, passer project_id et parent dans FormData pour chaque lot
        xhr.open('POST', 'assets/php/code_repo.php?action=upload');
        xhr.send(formData);
    });
}

// ============================================================
// Upload d'une sélection de fichiers (pas dossier)
// ============================================================
async function uploadFilesBatch(files, parent, projectId, progressModal) {
    const BATCH_SIZE = 10; // Réduction à 10 pour éviter HTTP 400
    let totalUploaded = 0;
    
    console.log(`Uploading ${files.length} files in batches of ${BATCH_SIZE}`);
    
    for (let i = 0; i < files.length; i += BATCH_SIZE) {
        const batch = files.slice(i, i + BATCH_SIZE);
        const batchNum = Math.floor(i / BATCH_SIZE) + 1;
        const totalBatches = Math.ceil(files.length / BATCH_SIZE);
        
        console.log(`Batch ${batchNum}/${totalBatches}: uploading ${batch.length} files`);
        
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('parent', parent);
        batch.forEach(f => {
            console.log(`  - ${f.name} (${f.size} bytes)`);
            formData.append('files[]', f, f.name);
        });
        
        try {
            const result = await uploadBatch(formData, projectId, (percent) => {
                const overallProgress = Math.round(((i + (batch.length * percent / 100)) / files.length) * 100);
                progressModal.update(overallProgress);
            });
            
            if (result.status === 'OK' && result.saved) {
                totalUploaded += result.saved.length;
                console.log(`Batch ${batchNum} OK: ${result.saved.length} fichiers`);
            } else if (result.error) {
                throw new Error(result.error);
            }
        } catch (e) {
            progressModal.close();
            showModalAlert(`Erreur batch ${batchNum}/${totalBatches}: ${e.message}`, { variant: 'error' });
            throw e;
        }
    }
    
    return totalUploaded;
}

// ============================================================
// Upload d'un dossier (FileSystem entries) avec chemins relatifs
// ============================================================
async function uploadFolderBatch(files, parent, projectId, progressModal) {
    const BATCH_SIZE = 10; // Réduction à 10 pour éviter HTTP 400
    let totalUploaded = 0;
    const filesArray = Array.from(files);
    
    console.log(`Uploading ${filesArray.length} files from folder in batches of ${BATCH_SIZE}`);
    
    for (let i = 0; i < filesArray.length; i += BATCH_SIZE) {
        const batch = filesArray.slice(i, i + BATCH_SIZE);
        const batchNum = Math.floor(i / BATCH_SIZE) + 1;
        const totalBatches = Math.ceil(filesArray.length / BATCH_SIZE);
        
        console.log(`Batch ${batchNum}/${totalBatches}: uploading ${batch.length} files`);
        
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('parent', parent);
        
        batch.forEach(f => {
            formData.append('files[]', f, f.name);
            formData.append('relativePaths[]', f.webkitRelativePath || f.name);
        });
        
        try {
            const result = await uploadBatch(formData, projectId, (percent) => {
                const overallProgress = Math.round(((i + (batch.length * percent / 100)) / filesArray.length) * 100);
                progressModal.update(overallProgress);
            });
            
            if (result.status === 'OK' && result.saved) {
                totalUploaded += result.saved.length;
                console.log(`Batch ${batchNum} OK: ${result.saved.length} fichiers`);
            } else if (result.error) {
                throw new Error(result.error);
            }
        } catch (e) {
            progressModal.close();
            showModalAlert(`Erreur batch ${batchNum}/${totalBatches}: ${e.message}`, { variant: 'error' });
            throw e;
        }
    }
    
    return totalUploaded;
}
