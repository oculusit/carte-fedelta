/*
 * FidAPPti - Loyalty Card Manager
 * Copyright (c) 2025 Alessandro "OCULUS" Blasi
 * https://www.oculus.it
 *
 * Licensed under the MIT License.
 * See LICENSE file in the project root for details.
 */

package it.oculus.carte;

import android.app.Activity;
import android.content.ContentValues;
import android.content.ContentResolver;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.MediaStore;
import android.util.Log;

import androidx.activity.result.ActivityResult;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.content.FileProvider;
import androidx.fragment.app.FragmentActivity;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;

@CapacitorPlugin(name = "FilePicker")
public class FilePickerPlugin extends Plugin {

    private static final String TAG = "FilePicker";
    private PluginCall pendingPickCall;
    private ActivityResultLauncher<Intent> pickLauncher;

    @Override
    public void load() {
        super.load();
        FragmentActivity activity = (FragmentActivity) getActivity();
        pickLauncher = activity.registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(),
            (ActivityResult result) -> {
                if (pendingPickCall == null) return;
                PluginCall call = pendingPickCall;
                pendingPickCall = null;

                if (result.getResultCode() != Activity.RESULT_OK || result.getData() == null) {
                    call.reject("Selezione annullata");
                    return;
                }

                Uri uri = result.getData().getData();
                if (uri == null) {
                    call.reject("Nessun file selezionato");
                    return;
                }

                try {
                    ContentResolver resolver = getContext().getContentResolver();
                    InputStream is = resolver.openInputStream(uri);
                    if (is == null) {
                        call.reject("Impossibile leggere il file");
                        return;
                    }
                    BufferedReader reader = new BufferedReader(new InputStreamReader(is, "UTF-8"));
                    StringBuilder sb = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) {
                        sb.append(line).append("\n");
                    }
                    reader.close();
                    is.close();

                    String fileName = uri.getLastPathSegment();
                    if (fileName == null) fileName = "file.json";

                    JSObject ret = new JSObject();
                    ret.put("content", sb.toString());
                    ret.put("fileName", fileName);
                    ret.put("uri", uri.toString());
                    call.resolve(ret);
                } catch (Exception e) {
                    Log.e(TAG, "pickFile read error", e);
                    call.reject("Errore lettura file: " + e.getMessage());
                }
            }
        );
    }

    @PluginMethod
    public void pickFile(PluginCall call) {
        if (pendingPickCall != null) {
            call.reject("Un'altra selezione file è già in corso");
            return;
        }

        String acceptType = call.getString("acceptType", "*/*");

        Intent intent = new Intent(Intent.ACTION_GET_CONTENT);
        intent.setType(acceptType);
        intent.addCategory(Intent.CATEGORY_OPENABLE);
        intent.putExtra(Intent.EXTRA_MIME_TYPES, new String[]{ acceptType });

        pendingPickCall = call;
        try {
            pickLauncher.launch(intent);
        } catch (Exception e) {
            pendingPickCall = null;
            Log.e(TAG, "pickFile launch error", e);
            call.reject("Impossibile aprire il selettore file: " + e.getMessage());
        }
    }

    @PluginMethod
    public void saveToDownloads(PluginCall call) {
        String filename = call.getString("filename", "backup.json");
        String data = call.getString("data", "");

        Log.d(TAG, "saveToDownloads: filename=" + filename + " dataLen=" + data.length());

        try {
            byte[] bytes = data.getBytes("UTF-8");

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                saveWithMediaStore(call, filename, bytes);
            } else {
                saveWithExternalFilesDir(call, filename, bytes);
            }
        } catch (Exception e) {
            Log.e(TAG, "saveToDownloads error", e);
            call.reject("Errore salvataggio: " + e.getMessage());
        }
    }

    private void saveWithMediaStore(PluginCall call, String filename, byte[] bytes) throws Exception {
        ContentResolver resolver = getContext().getContentResolver();

        ContentValues values = new ContentValues();
        values.put(MediaStore.Downloads.DISPLAY_NAME, filename);
        values.put(MediaStore.Downloads.MIME_TYPE, "application/json");
        values.put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS);

        Uri uri = resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values);

        if (uri == null) {
            Log.e(TAG, "MediaStore insert returned null");
            call.reject("Impossibile creare il file nella cartella Download");
            return;
        }

        Log.d(TAG, "MediaStore URI: " + uri);

        OutputStream os = resolver.openOutputStream(uri, "wt");
        if (os == null) {
            call.reject("Impossibile aprire lo stream di scrittura");
            return;
        }

        os.write(bytes);
        os.flush();
        os.close();

        Log.d(TAG, "Written " + bytes.length + " bytes via MediaStore");

        String downloadPath = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS) + "/" + filename;

        JSObject result = new JSObject();
        result.put("uri", uri.toString());
        result.put("path", downloadPath);
        result.put("filename", filename);
        result.put("size", bytes.length);
        call.resolve(result);
    }

    private void saveWithExternalFilesDir(PluginCall call, String filename, byte[] bytes) throws Exception {
        File dir = getContext().getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
        if (dir == null) {
            call.reject("Cartella Download non disponibile");
            return;
        }
        if (!dir.exists()) {
            dir.mkdirs();
        }

        File file = new File(dir, filename);
        FileOutputStream fos = new FileOutputStream(file);
        fos.write(bytes);
        fos.flush();
        fos.close();

        Log.d(TAG, "Written " + bytes.length + " bytes to " + file.getAbsolutePath());

        JSObject result = new JSObject();
        result.put("uri", Uri.fromFile(file).toString());
        result.put("path", file.getAbsolutePath());
        result.put("filename", filename);
        result.put("size", bytes.length);
        call.resolve(result);
    }

    @PluginMethod
    public void shareFile(PluginCall call) {
        String filename = call.getString("filename", "backup.json");
        String data = call.getString("data", "");
        String title = call.getString("title", "Condividi file");
        String text = call.getString("text", "");

        try {
            byte[] bytes = data.getBytes("UTF-8");

            File cacheDir = new File(getContext().getCacheDir(), "share");
            if (!cacheDir.exists()) cacheDir.mkdirs();
            File file = new File(cacheDir, filename);
            FileOutputStream fos = new FileOutputStream(file);
            fos.write(bytes);
            fos.flush();
            fos.close();

            Log.d(TAG, "shareFile: wrote " + bytes.length + " bytes to " + file.getAbsolutePath());

            Uri contentUri = FileProvider.getUriForFile(
                getContext(),
                getContext().getPackageName() + ".fileprovider",
                file
            );

            Intent intent = new Intent(Intent.ACTION_SEND);
            intent.setType("application/json");
            intent.putExtra(Intent.EXTRA_STREAM, contentUri);
            intent.putExtra(Intent.EXTRA_TITLE, title);
            if (text != null && !text.isEmpty()) {
                intent.putExtra(Intent.EXTRA_TEXT, text);
            }
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getActivity().startActivity(Intent.createChooser(intent, title));

            call.resolve();
        } catch (Exception e) {
            Log.e(TAG, "shareFile error", e);
            call.reject("Impossibile condividere: " + e.getMessage());
        }
    }

    @PluginMethod
    public void openDownloadsFolder(PluginCall call) {
        try {
            Intent intent = new Intent(Intent.ACTION_VIEW);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                Uri treeUri = Uri.parse("content://com.android.externalstorage.documents/document/primary%3ADownload");
                intent.setDataAndType(treeUri, "vnd.android.document/directory");
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            } else {
                File dir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS);
                if (dir == null || !dir.exists()) {
                    dir = getContext().getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
                }
                if (dir == null) {
                    call.reject("Cartella Download non disponibile");
                    return;
                }
                Uri uri = Uri.fromFile(dir);
                intent.setDataAndType(uri, "resource/folder");
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            }
            getActivity().startActivity(intent);
            call.resolve();
        } catch (Exception e) {
            Log.e(TAG, "openDownloadsFolder error", e);
            // Fallback: open Downloads via DownloadManager
            try {
                Intent fallback = new Intent(android.app.DownloadManager.ACTION_VIEW_DOWNLOADS);
                fallback.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                getActivity().startActivity(fallback);
                call.resolve();
            } catch (Exception e2) {
                call.reject("Impossibile aprire la cartella: " + e2.getMessage());
            }
        }
    }
}
