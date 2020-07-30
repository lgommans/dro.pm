package dropm.luc;

import android.app.Activity;
import android.widget.TextView;
import android.os.Bundle;
import android.content.Intent;
import android.content.Context;
import android.content.ClipboardManager;
import android.content.ClipData;
import android.net.Uri;
import android.provider.MediaStore;
import android.database.Cursor;
import java.io.FileInputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.io.File;
import java.io.PrintWriter;
import java.io.StringWriter;
import java.net.Socket;
import android.text.method.ScrollingMovementMethod;

public class dropmactivity extends Activity {
	/** Called when the activity is first created. */
	@Override
	public void onCreate(Bundle savedInstanceState) {
		super.onCreate(savedInstanceState);
		setContentView(R.layout.main);

		TextView out = (TextView) findViewById(R.id.output);
		out.setMovementMethod(new ScrollingMovementMethod());
		out.setText("This app only gives you the dro.pm option in your 'Share' menu. It currently does nothing else. Settings are still TODO.\n\n"
			+ "Of course, you are welcome to contribute on Github! See github.com/lgommans/dro.pm\n"
			+ "Or complain using the 'Report a bug' feature about what you want. See the dro.pm homepage.");

		Intent intent = getIntent();
		String action = intent.getAction();
		String type = intent.getType();

		if (Intent.ACTION_SEND.equals(action) && type != null) {
			if ("text/plain".equals(type)) {
				out.setText("This is the text to be shared (currently not implemented): " + intent.getStringExtra(Intent.EXTRA_TEXT));
			}
			else {
				out.setText("Loading file...");

				// Determine the path, which might be file:// or content:// as far as I know (are there more? Is there a list of these?)
				Uri stream = (Uri) intent.getParcelableExtra(Intent.EXTRA_STREAM);
				String path = stream.toString();
				if (path.indexOf("file://") == 0) {
					path = path.substring("file://".length());
				}
				else if (path.indexOf("content://") == 0) {
					path = getRealPathFromUri(getApplicationContext(), stream);
				}
				File file = new File(path);
				if (!file.exists()) {
					out.setText("I received a request to share <" + path + "> but I cannot find this file.");
					return;
				}
				String fname = file.getName();
				long filesize = (long) file.length();
				long maxFileSize = 1024 * 1024 * 1024 * 5;
				if (filesize > maxFileSize) {
					out.setText("File too big (5GB max currently). You can complain about this using the 'Report a bug' feature on the website.");
					return;
				}

				// From Emmanuel, https://stackoverflow.com/a/4126746
				// cc by-sa 3.0 with attribution required
				try {
					out.setText("Connecting to https://dro.pm...");

					java.net.URL url = new java.net.URL("https://dro.pm/fileman.php");
					java.net.HttpURLConnection huc = (java.net.HttpURLConnection) url.openConnection();
					huc.setDoOutput(true);
					huc.setRequestMethod("POST");

					String bound = "ubiYu9a4DvrqDBsbDNGxoxG1hZZ45F4HnPX1jTnU"; // TODO randomize
					String bodyStart = "--" + bound + "\r\n"
						+ "Content-Disposition: form-data; name=\"f\"; filename=\"" + fname + "\"\r\n"
						+ "Content-Type: application/octet-stream\r\n"
						+ "\r\n";
					String bodyEnd = "\r\n--" + bound;

					huc.setRequestProperty("Content-type", "multipart/form-data; boundary=" + bound);
					huc.setRequestProperty("Content-Length", "" + (bodyStart.length() + filesize + bodyEnd.length()));
					huc.setRequestProperty("User-Agent", "dro.pm-androidapp/0.2");

					java.io.DataOutputStream outstream = new java.io.DataOutputStream(huc.getOutputStream());

					outstream.writeBytes(bodyStart);

					// Copy the file in `buffersize` increments to the network
					out.setText("Uploading file...");
					FileInputStream fis = new FileInputStream(file);
					int buffersize = 1024 * 256;
					int bytesSent = 0;
					byte[] b = new byte[buffersize];
					while (true) {
						if (fis.available() < buffersize) {
							buffersize = fis.available();
						}
						fis.read(b, 0, buffersize);
						outstream.write(b, 0, buffersize);
						bytesSent += buffersize;
						if (fis.available() <= 0 || bytesSent > maxFileSize) {
							// The `bytesSent > maxFileSize` is also to limit the while(true) loop in case something acts weird.
							break;
						}
					}

					// Finish up and let's see if we receive anything.
					outstream.writeBytes(bodyEnd);
					out.setText("Waiting for upload to finish...");

					InputStream responseStream = new java.io.BufferedInputStream(huc.getInputStream());
					java.io.BufferedReader responseStreamReader = new java.io.BufferedReader(new java.io.InputStreamReader(responseStream));
					int timeout = 60; // We don't know if everything left the network transmit buffer already, so better set a high limit.
					int starttime = currentUnixTime();
					String line = "";
					StringBuilder stringBuilder = new StringBuilder();
					String received;
					while ((line = responseStreamReader.readLine()) != null) {
						stringBuilder.append(line).append("\n");
					}
					responseStreamReader.close();

					received = stringBuilder.toString();
					String needle = "dro.pm/";
					int needlepos = received.indexOf(needle);
					if (needlepos != -1) {
						String shortLink = received.substring(needlepos);
						String text = "Download the file at:\n\n" + shortLink;
						if (clip("https://" + shortLink)) {
							text += "\n\nThe link has been copied to your clipboard.";
						}
						else {
							text += "\n\nThe link could not be copied to your clipboard because this app does not support Android before version 3.0. "
								+ "You can complain about this on dro.pm using the 'Report a bug' feature.";
						}
						out.setText(text);
						return;
					}
					if (currentUnixTime() - starttime > timeout) {
						out.setText("Timeout while waiting for a response. Received (if anything): \"" + received + "\"");
						return;
					}
				}
				catch (Exception e) {
					// Convert the full stack trace to a string and display it.
					String msg = "Sorry, something somewhere went wrong. Below you can see all the details. Please try again or submit a bug report at dro.pm using the 'Report a bug' feature.\n\n\n\n";
					StringWriter sw = new StringWriter();
					PrintWriter pw = new PrintWriter(sw);
					e.printStackTrace(pw);
					out.setText(msg + sw.toString());
				}
			}
		}
	}

	private int currentUnixTime() {
		return (int)(System.currentTimeMillis() / 1000);
	}

	public static String getRealPathFromUri(Context context, Uri contentUri) {
		// From Selecsosi, https://stackoverflow.com/a/20059657
		// cc by-sa 3.0 with attribution required
		Cursor cursor = null;
		try {
			String[] proj = { MediaStore.Images.Media.DATA };
			cursor = context.getContentResolver().query(contentUri, proj, null, null, null);
			int column_index = cursor.getColumnIndexOrThrow(MediaStore.Images.Media.DATA);
			cursor.moveToFirst();
			return cursor.getString(column_index);
		} finally {
			if (cursor != null) {
				cursor.close();
			}
		}
	}

	private boolean clip(String text) {
		try {
			ClipboardManager cm = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
			ClipData clip = ClipData.newPlainText("Your short link", text);
			cm.setPrimaryClip(clip);
			return true;
		}
		catch (Exception e) {
			return false;
		}
	}
}

