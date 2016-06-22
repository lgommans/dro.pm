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
				int filesize = (int) file.length();
				int maxFileSize = 1024 * 1024 * 128; // Currently you can't upload big files, and it makes for a hard limit on a while(true) loop below.
				if (filesize > maxFileSize) {
					out.setText("File too big (about 50MB max currently). You can complain about this using the 'Report a bug' feature on the website.");
					return;
				}

				// From Emmanuel, http://stackoverflow.com/a/4126746
				// cc by-sa 3.0 with attribution required
				try {
					out.setText("Connecting to dro.pm...");
					Socket post = new Socket("dro.pm", 80);
					OutputStream outstream = post.getOutputStream();

					// Build some body parts already, to be used in the content-length calculation
					String bound = "ubiYu9a4DvrqDBsbDNGxoxG1hZZ45F4HnPX1jTnU";
					String bodyStart = "--" + bound + "\r\n"
						+ "Content-Disposition: form-data; name=\"f\"; filename=\"" + fname + "\"\r\n"
						+ "Content-Type: application/octet-stream\r\n"
						+ "\r\n";
					String bodyEnd = "\r\n--" + bound;

					// Write the headers and the start of the body
					outstream.write(("POST /fileman.php HTTP/1.1\r\n"
							+ "Host: dro.pm\r\n"
							+ "User-Agent: dro.pm-androidapp\r\n"
							+ "Connection: close\r\n"
							+ "Content-Type: multipart/form-data; boundary=" + bound + "\r\n"
							+ "Content-Length: " + (bodyStart.length() + filesize + bodyEnd.length()) + "\r\n"
							+ "\r\n"
							+ bodyStart
						).getBytes());

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
					outstream.write(bodyEnd.getBytes());
					out.setText("Waiting for upload to finish...");

					InputStream in = post.getInputStream();
					String received = "";
					int timeout = 60; // We don't know if everything left the network transmit buffer already, so better set a high limit.
					int starttime = currentUnixTime();
					while (true) {
						if (in.available() > 0) {
							int readBytes = in.available();
							in.read(b, 0, readBytes);
							received += new String(b).substring(0, readBytes);
						}
						if (received.contains("\r\n")) {
							// We have received at least the status line
							String status = received.substring(received.indexOf(" ") + 1);
							status = status.substring(0, status.indexOf(" "));
							if (!status.equals("200")) {
								out.setText("Error in server communication (status " + status + "). This is the raw response:\n" + received);
								return;
							}
						}
						String needle = "dro.pm/";
						int needlepos = received.indexOf(needle);
						if (needlepos != -1) {
							// There might be an edge case in which "dro.pm/" is already downloaded, but not the (complete) URI. We are ignoring this for now because
							// I do not feel like parsing the headers to find the Content-Length header, etc.
							String shortLink = received.substring(needlepos);
							String text = "Download the file at:\n\n" + shortLink;
							if (clip("http://" + shortLink)) {
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
							out.setText("Time-out while waiting for a response. Received (if anything): '" + received + "'");
							return;
						}
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
		// From Selecsosi, http://stackoverflow.com/a/20059657
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

